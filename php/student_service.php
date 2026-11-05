<?php
declare(strict_types=1);

/**
 * e-Lesson Student Service
 *
 * Reusable student-account creation logic, shared by the admin "Add Student" form
 * (admin/students/index.php) and, later, bulk Excel/CSV import. Creating a student
 * here has zero side effects beyond the users row itself — no course_subs, payment,
 * transaction, test_results, or progress row is ever created as part of student
 * creation. Enrollment, payment, and access are separate, later admin actions.
 */

require_once __DIR__ . '/../login/Login_functions.php';

class StudentService
{
    /**
     * Allowed individual student grade levels (1–11). Distinct from course
     * classLevel ranges like '7-8' used in admin/courses/edit.php.
     */
    public const CLASS_LEVELS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11'];

    /**
     * Create a new student account for the given tenant.
     *
     * @param PDO   $pdo
     * @param int   $tenantId Trusted tenant ID from the caller's own session context
     *                        (e.g. Tenant::getId()) — this function never reads tenant
     *                        scope from $input, so a caller cannot be tricked into
     *                        creating a student in another tenant via a form field.
     * @param array $input    Raw input: first_name, last_name, email, phone, class, password.
     * @return array{success: bool, errors?: array<string,string>, user_id?: int, slug?: string}
     */
    public static function create(PDO $pdo, int $tenantId, array $input): array
    {
        $firstName = trim((string)($input['first_name'] ?? ''));
        $lastName  = trim((string)($input['last_name'] ?? ''));
        $email     = trim((string)($input['email'] ?? ''));
        $phoneRaw  = trim((string)($input['phone'] ?? ''));
        $class     = trim((string)($input['class'] ?? ''));
        $password  = (string)($input['password'] ?? '');

        $errors = [];

        if ($tenantId <= 0) {
            // Defensive: should never happen when the caller passes Tenant::getId(),
            // but never proceed without a valid, trusted tenant scope.
            $errors['tenant_id'] = 'Не удалось определить образовательный центр.';
        }
        if ($firstName === '') {
            $errors['first_name'] = 'Имя обязательно.';
        }
        if ($lastName === '') {
            $errors['last_name'] = 'Фамилия обязательна.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Введите корректный email.';
        }
        if (!in_array($class, self::CLASS_LEVELS, true)) {
            $errors['class'] = 'Выберите класс от 1 до 11.';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'Пароль должен содержать не менее 6 символов.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Tenant-scoped duplicate check — matches the existing convention in
        // login/register.php (SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?).
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?");
        $stmt->execute([$email, $tenantId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['email' => 'Студент с таким email уже существует в этом центре.']];
        }

        $phone = $phoneRaw !== '' ? formatToUzPhone($phoneRaw) : '';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $maxAttempts = 2;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO users (tenant_id, first_name, last_name, email, password, phone, class, points, balance, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1)"
                );
                $stmt->execute([$tenantId, $firstName, $lastName, $email, $hashedPassword, $phone, $class]);

                $userId = (int)$pdo->lastInsertId();

                // Slug generation matches the existing convention used at self-registration
                // (login/register.php + login/Login_functions.php::generateSlug()).
                $slug = generateSlug($pdo, $firstName, $lastName);
                if ($attempt > 1) {
                    $slug .= '-' . random_int(100, 999);
                }
                $upd = $pdo->prepare("UPDATE users SET slug = ? WHERE user_id = ? AND tenant_id = ?");
                $upd->execute([$slug, $userId, $tenantId]);

                $pdo->commit();

                return ['success' => true, 'user_id' => $userId, 'slug' => $slug];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    $message = $e->getMessage();
                    if (stripos($message, 'email') !== false || stripos($message, 'idx_users_email_tenant') !== false) {
                        // Race-condition backstop: the (email, tenant_id) unique index caught a
                        // duplicate our pre-check missed under concurrent requests.
                        return ['success' => false, 'errors' => ['email' => 'Студент с таким email уже существует в этом центре.']];
                    }
                    // Collision is on users.slug (global UNIQUE KEY), not email — retry once
                    // with a randomized suffix instead of misreporting it as an email conflict.
                    if ($attempt < $maxAttempts) {
                        continue;
                    }
                    return ['success' => false, 'errors' => ['general' => 'Произошла ошибка при создании студента.']];
                }
                error_log('[StudentService] create() failed: ' . $e->getMessage());
                return ['success' => false, 'errors' => ['general' => 'Произошла ошибка при создании студента.']];
            }
        }
    }
}
