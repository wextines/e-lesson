<?php
require '../../php/config.php'; // Подключение к базе

// ─── Verify this request actually came from Telegram ───────────
// Telegram's own authentication mechanism for webhooks: the secret configured via the
// `secret_token` parameter of setWebhook (https://core.telegram.org/bots/api#setwebhook)
// is echoed back on every real update as the X-Telegram-Bot-Api-Secret-Token header.
// Fails closed: until TELEGRAM_WEBHOOK_SECRET is configured (and registered with Telegram),
// this endpoint accepts nothing at all, from anyone, before any request body is even read.
$webhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: '';
$providedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($webhookSecret === '' || !hash_equals($webhookSecret, $providedSecret)) {
    http_response_code(401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Проверка callback
if (isset($input['callback_query'])) {
    $callback = $input['callback_query'];
    $callback_id = $callback['id'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $data = $callback['data'];

    // Функция для отправки ответа
    function answerCallback($callback_id, $text = '', $show_alert = false)
    {
        $url = "https://api.telegram.org/bot{$GLOBALS['bot_token']}/answerCallbackQuery";
        file_get_contents($url . '?' . http_build_query([
            'callback_query_id' => $callback_id,
            'text' => $text,
            'show_alert' => $show_alert ? 'true' : 'false'
        ]));
    }

    // Обработка действия
    if (preg_match('/^payment_(accept|reject|wrong_amount)_(\d+)$/', $data, $matches)) {
        $action = $matches[1];
        $payment_id = (int)$matches[2];

        // Получаем инфу о платеже (tenant_id берём из записи платежа)
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND tenant_id IS NOT NULL");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            answerCallback($callback_id, "To'lov topilmadi.");
            exit();
        }

        $user_id = $payment['user_id'];
        $amount = $payment['amount'];

        switch ($action) {
            case 'accept':
                if ($payment['status'] === 'accepted') {
                    answerCallback($callback_id, "To'lov allaqachon qabul qilingan.");
                    exit();
                }

                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("UPDATE payments SET status = 'accepted' WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'");
                    $upd->execute([$payment_id, $payment['tenant_id']]);
                    if ($upd->rowCount() === 0) {
                        $pdo->rollBack();
                        answerCallback($callback_id, "To'lov allaqachon qabul qilingan.");
                        exit();
                    }

                    if (!empty($payment['course_id'])) {
                        $stmt = $pdo->prepare("SELECT subscription_days FROM courses WHERE course_id = ? AND tenant_id = ?");
                        $stmt->execute([$payment['course_id'], $payment['tenant_id']]);
                        $days = (int)$stmt->fetchColumn();
                        if ($days <= 0) {
                            $pdo->rollBack();
                            answerCallback($callback_id, "Xatolik: Kurs obuna muddati ko'rsatilmagan.");
                            exit();
                        }

                        $stmt = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
                        $stmt->execute([$user_id, $payment['course_id'], $payment['tenant_id']]);
                        if ($stmt->rowCount() > 0) {
                            $pdo->prepare("UPDATE course_subs SET access_expires_at = DATE_ADD(GREATEST(COALESCE(access_expires_at, NOW()), NOW()), INTERVAL ? DAY) WHERE user_id = ? AND course_id = ? AND tenant_id = ?")
                                ->execute([$days, $user_id, $payment['course_id'], $payment['tenant_id']]);
                        } else {
                            $pdo->prepare("INSERT INTO course_subs (user_id, course_id, price, purchase_date, access_expires_at, tenant_id) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?)")
                                ->execute([$user_id, $payment['course_id'], $payment['amount'], $days, $payment['tenant_id']]);
                        }
                    }
                    $pdo->commit();
                    answerCallback($callback_id, "✅ To'lov qabul qilindi.");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    answerCallback($callback_id, "Xatolik yuz berdi: " . $e->getMessage());
                    exit();
                }
                break;

            case 'reject':
                $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE payment_id = ? AND tenant_id = ?")->execute([$payment_id, $payment['tenant_id']]);
                answerCallback($callback_id, "❌ To'lov rad etildi.");
                break;

            case 'wrong_amount':
                $pdo->prepare("UPDATE payments SET status = 'waiting_amount' WHERE payment_id = ? AND tenant_id = ?")->execute([$payment_id, $payment['tenant_id']]);
                // Просим администратора ввести правильную сумму
                $url = "https://api.telegram.org/bot{$GLOBALS['bot_token']}/sendMessage";
                file_get_contents($url . '?' . http_build_query([
                    'chat_id' => $chat_id,
                    'text' => "Iltimos, to‘g‘ri summani kiriting (payment ID: $payment_id):"
                ]));
                answerCallback($callback_id, "Summani kiriting...");
                break;
        }

        // Обновляем подпись и убираем inline-кнопки (reply_markup = пустой)
        $status_map = [
            'accept' => '✅ Qabul qilindi',
            'reject' => '❌ Rad etildi',
            'wrong_amount' => '⚠️ Noto‘g‘ri summa'
        ];

        $old_caption = $callback['message']['caption'] ?? '';

        // Удаляем старый статус
        $clean_caption = preg_replace('/\n*Holati: [^\n]*/u', '', $old_caption);

        // Формируем новую подпись
        $new_caption = trim($clean_caption) . "\n\nHolati: " . ($status_map[$action] ?? $action);

        // Проверяем кодировку
        $new_caption = mb_convert_encoding($new_caption, 'UTF-8', 'auto');

        // Извлекаем caption_entities из callback_query
        $entities = $callback['message']['caption_entities'] ?? [];

        // Корректируем offset для caption_entities
        $new_entities = [];
        foreach ($entities as $entity) {
            if ($entity['offset'] < strlen($clean_caption)) {
                $new_entities[] = [
                    'type' => $entity['type'],
                    'offset' => $entity['offset'],
                    'length' => $entity['length'],
                    'url' => $entity['url'] ?? null,
                ];
            }
        }

        // Обновляем подпись с caption_entities
        $edit_url = "https://api.telegram.org/bot{$GLOBALS['bot_token']}/editMessageCaption";
        $response = file_get_contents($edit_url . '?' . http_build_query([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'caption' => $new_caption,
            'parse_mode' => 'HTML',
            'caption_entities' => json_encode($new_entities),
            'reply_markup' => json_encode(new stdClass())
        ]));

        exit();
    }
} elseif (isset($input['message'])) {
    // Обработка текстовых сообщений (ввод суммы администратором)
    $chat_id = $input['message']['chat']['id'];
    $text = trim($input['message']['text'] ?? '');
    $replyToText = $input['message']['reply_to_message']['text'] ?? '';

    // Correlate this reply to the specific payment the bot actually prompted for (the
    // payment ID is already embedded in the bot's own "wrong_amount" message below), instead
    // of grabbing whichever payment happens to be waiting_amount anywhere on the platform —
    // that used to let an unrelated message land on and corrupt a different payment's amount.
    if (!preg_match('/payment ID:\s*(\d+)/i', $replyToText, $ridMatch)) {
        exit();
    }
    $expectedPaymentId = (int)$ridMatch[1];

    // Проверяем, есть ли платеж в статусе waiting_amount
    $stmt = $pdo->prepare("SELECT payment_id, tenant_id FROM payments WHERE payment_id = ? AND status = 'waiting_amount'");
    $stmt->execute([$expectedPaymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        if (is_numeric($text) && $text > 0) {
            $new_amount = floatval($text);
            $payment_id = $payment['payment_id'];
            $tenant_id = $payment['tenant_id'];

            // Обновляем сумму и ставим статус amount_corrected
            $pdo->prepare("UPDATE payments SET amount = ?, status = 'amount_corrected' WHERE payment_id = ? AND tenant_id = ?")
                ->execute([$new_amount, $payment_id, $tenant_id]);

            // Отправляем подтверждение админу
            $url = "https://api.telegram.org/bot{$GLOBALS['bot_token']}/sendMessage";
            file_get_contents($url . '?' . http_build_query([
                'chat_id' => $chat_id,
                'text' => "Summasi muvaffaqiyatli yangilandi: {$new_amount} so'm."
            ]));
        } else {
            $url = "https://api.telegram.org/bot{$GLOBALS['bot_token']}/sendMessage";
            file_get_contents($url . '?' . http_build_query([
                'chat_id' => $chat_id,
                'text' => "Iltimos, to‘g‘ri raqam kiriting."
            ]));
        }
        exit();
    }
}