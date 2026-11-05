<?php
declare(strict_types=1);

/**
 * e-Lesson Payment Service
 *
 * Implements the agreed target course-access/payment state machine (README section 12,
 * CLAUDE.md rules 4-5). Two entry points, both admin-initiated:
 *   - grantFirstAccess(): "Выдать доступ" — first payment on a pending subscription.
 *   - renew():            "Продлить курс" — a subsequent payment on an active subscription.
 * Both create exactly one transactions row and are the only code paths that should ever
 * move a subscription pending -> active, or advance an active subscription's schedule,
 * under the new model. The student-facing receipt upload flow (profile/payments.php) is
 * untouched — it only ever creates a `payments` row, never mutates course_subs directly.
 * The legacy admin acceptance paths (admin/payments/index.php, admin/payments/view.php,
 * admin/students/view.php's update_payment) call into this same service (P0-4) instead of
 * mutating course_subs/access_expires_at themselves.
 */

class PaymentService
{
    public const PAYMENT_METHODS = ['cash', 'card', 'transfer'];

    /**
     * Maps the legacy `payments.payment_method` vocabulary ('cash'/'terminal'/'online')
     * onto the `transactions.payment_method` schema enum ('cash'/'card'/'transfer'), so
     * the legacy admin acceptance paths can feed a payments row into this service without
     * asking the admin to re-select a payment method that was already recorded.
     */
    public static function mapLegacyPaymentMethod(?string $legacyMethod): string
    {
        return match ($legacyMethod) {
            'cash' => 'cash',
            'terminal' => 'card',
            'online' => 'transfer',
            default => 'cash',
        };
    }

    /**
     * First payment on a pending subscription: activates access and records the first
     * transaction. Fails (no state change, no transaction) if the subscription is not
     * currently 'pending', or if $input fails validation.
     *
     * @return array{success: bool, errors?: array<string,string>}
     */
    public static function grantFirstAccess(PDO $pdo, int $tenantId, int $subscriptionId, array $input, string $adminUsername): array
    {
        $validated = self::validateInput($input, true);
        if (!empty($validated['errors'])) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        try {
            $pdo->beginTransaction();

            // Lock the row and re-check its status inside the transaction: this is both
            // the tenant/ownership check and the guard against a double submission
            // racing to activate the same pending subscription twice.
            $stmt = $pdo->prepare(
                "SELECT subscription_id, user_id, course_id, status FROM course_subs
                 WHERE subscription_id = ? AND tenant_id = ? FOR UPDATE"
            );
            $stmt->execute([$subscriptionId, $tenantId]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sub) {
                $pdo->rollBack();
                return ['success' => false, 'errors' => ['general' => 'Подписка не найдена.']];
            }
            if ($sub['status'] !== 'pending') {
                $pdo->rollBack();
                return ['success' => false, 'errors' => ['general' => 'Подписка уже не в статусе "ожидает оплаты" — обновите страницу.']];
            }

            $pdo->prepare(
                "UPDATE course_subs SET status = 'active', access_expires_at = ?, next_payment_date = ?
                 WHERE subscription_id = ? AND tenant_id = ?"
            )->execute([$validated['nextPaymentDate'], $validated['nextPaymentDate'], $subscriptionId, $tenantId]);

            $pdo->prepare(
                "INSERT INTO transactions (tenant_id, user_id, course_sub_id, source_payment_id, amount, payment_date, payment_method, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $tenantId, $sub['user_id'], $subscriptionId, self::sourcePaymentId($input),
                $validated['amount'], $validated['paymentDate'], $validated['method'], $validated['notes'], $adminUsername,
            ]);

            $pdo->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[PaymentService] grantFirstAccess() failed: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['general' => 'Произошла ошибка при выдаче доступа.']];
        }
    }

    /**
     * Renewal on an active subscription: advances next_payment_date from its CURRENT
     * planned value (never from today) by the course's subscription_days, and records
     * another transaction. Fails if the subscription is not currently 'active'. An
     * optional $input['next_payment_date'] (Y-m-d) overrides the computed date.
     *
     * @return array{success: bool, errors?: array<string,string>, next_payment_date?: string}
     */
    public static function renew(PDO $pdo, int $tenantId, int $subscriptionId, array $input, string $adminUsername): array
    {
        $validated = self::validateInput($input, false);
        if (!empty($validated['errors'])) {
            return ['success' => false, 'errors' => $validated['errors']];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT cs.subscription_id, cs.user_id, cs.course_id, cs.status, cs.next_payment_date, c.subscription_days
                 FROM course_subs cs
                 JOIN courses c ON c.course_id = cs.course_id AND c.tenant_id = cs.tenant_id
                 WHERE cs.subscription_id = ? AND cs.tenant_id = ? FOR UPDATE"
            );
            $stmt->execute([$subscriptionId, $tenantId]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sub) {
                $pdo->rollBack();
                return ['success' => false, 'errors' => ['general' => 'Подписка не найдена.']];
            }
            if ($sub['status'] !== 'active') {
                $pdo->rollBack();
                return ['success' => false, 'errors' => ['general' => 'Продлить можно только активную подписку.']];
            }
            $subscriptionDays = (int)$sub['subscription_days'];
            if ($subscriptionDays <= 0) {
                $pdo->rollBack();
                return ['success' => false, 'errors' => ['general' => 'У курса не задан срок подписки (subscription_days).']];
            }
            if (empty($sub['next_payment_date'])) {
                $pdo->rollBack();
                return ['success' => false, 'errors' => ['general' => 'У подписки не задана текущая дата следующего платежа.']];
            }

            // Advance strictly from the CURRENT planned date, never from today's date —
            // unless the admin explicitly overrides it via next_payment_date (e.g. to
            // correct the auto-computed date in the "Продлить курс" form).
            $overrideNextPaymentDate = trim((string)($input['next_payment_date'] ?? ''));
            if ($overrideNextPaymentDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $overrideNextPaymentDate)) {
                $newNextPaymentDate = $overrideNextPaymentDate;
            } else {
                $newNextPaymentDate = date('Y-m-d', strtotime($sub['next_payment_date'] . " +{$subscriptionDays} days"));
            }

            $pdo->prepare(
                "UPDATE course_subs SET next_payment_date = ?, access_expires_at = ?
                 WHERE subscription_id = ? AND tenant_id = ?"
            )->execute([$newNextPaymentDate, $newNextPaymentDate, $subscriptionId, $tenantId]);

            $pdo->prepare(
                "INSERT INTO transactions (tenant_id, user_id, course_sub_id, source_payment_id, amount, payment_date, payment_method, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $tenantId, $sub['user_id'], $subscriptionId, self::sourcePaymentId($input),
                $validated['amount'], $validated['paymentDate'], $validated['method'], $validated['notes'], $adminUsername,
            ]);

            $pdo->commit();
            return ['success' => true, 'next_payment_date' => $newNextPaymentDate];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[PaymentService] renew() failed: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['general' => 'Произошла ошибка при продлении подписки.']];
        }
    }

    /**
     * @return array{errors: array<string,string>, amount?: float, method?: string, paymentDate?: string, nextPaymentDate?: ?string, notes?: ?string}
     */
    /**
     * Links the ledger entry to the legacy `payments` receipt that produced it, when the
     * caller is one of the receipt-acceptance paths. NULL for a payment an admin recorded
     * directly. This is what lets finance reporting treat `transactions` as the single
     * source of truth without double-counting accepted receipts (see
     * migrations/005_finance_ledger_hardening.sql).
     */
    private static function sourcePaymentId(array $input): ?int
    {
        $id = (int)($input['source_payment_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private static function validateInput(array $input, bool $requireNextPaymentDate): array
    {
        $errors = [];

        $amount = (float)($input['amount'] ?? 0);
        if ($amount <= 0) {
            $errors['amount'] = 'Введите сумму больше 0.';
        }

        $method = trim((string)($input['payment_method'] ?? ''));
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            $errors['payment_method'] = 'Выберите способ оплаты.';
        }

        $paymentDate = trim((string)($input['payment_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $errors['payment_date'] = 'Укажите дату платежа.';
        }

        $nextPaymentDate = null;
        if ($requireNextPaymentDate) {
            $nextPaymentDate = trim((string)($input['next_payment_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextPaymentDate)) {
                $errors['next_payment_date'] = 'Укажите дату следующего платежа.';
            }
        }

        $notes = trim((string)($input['notes'] ?? ''));

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return [
            'errors' => [],
            'amount' => $amount,
            'method' => $method,
            'paymentDate' => $paymentDate,
            'nextPaymentDate' => $nextPaymentDate,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }
}
