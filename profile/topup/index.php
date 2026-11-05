<?php
require '../../php/config.php';
require '../../login/Login_functions.php';
require '../../php/tenant_file.php';

// Balance top-up is deprecated in the new MVP flow. Redirect to payments.
header("Location: /profile/payments.php");
exit();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($_FILES['receipt']) &&
        is_uploaded_file($_FILES['receipt']['tmp_name']) &&
        !empty($_POST['amount']) &&
        is_numeric($_POST['amount']) &&
        floatval($_POST['amount']) > 0
    ) {
        $amount = floatval($_POST['amount']);
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

        $file_type = mime_content_type($_FILES['receipt']['tmp_name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file_type, $allowed_types)) {
            $error = "Faqat JPEG, PNG yoki GIF formatidagi cheklar qabul qilinadi.";
        } else {
            $uploadResult = TenantFile::handleUpload(
                $_FILES['receipt'],
                Tenant::getId(),
                'receipts'
            );

            if (!$uploadResult) {
                $error = "Faylni saqlab bo'lmadi.";
            } else {
                $relativePath = $uploadResult['relativePath'];
                $destPath = $uploadResult['fullPath'];
                $stmt = $pdo->prepare("
            INSERT INTO payments (user_id, amount, payment_date, status, receipt_path, comment, tenant_id)
            VALUES (?, ?, NOW(), 'pending', ?, ?, ?)
        ");
                $stmt->execute([$user_id, $amount, $relativePath, $comment, Tenant::getId()]);
                $payment_id = $pdo->lastInsertId();
                $link = "https://abdubakiyev.uz/profile/index.php?user_id=" . urlencode($user_id);
                $linkUser = "<a href='{$link}'>{$first_name} {$last_name}</a>";

                $caption = "📥 Balans to'ldirish so'rovi\n" .
                    "🆔 User ID: {$user_id}\n" .
                    "👤 $linkUser\n" .
                    "💰 Summa: {$amount} sum\n" .
                    "🆔 Payment ID: {$payment_id}" .
                    ($comment ? "\n💬 Izoh: {$comment}" : "");

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Не получено', 'callback_data' => "payment_reject_{$payment_id}"],
                            ['text' => 'Получено', 'callback_data' => "payment_accept_{$payment_id}"],
                            ['text' => 'Сумма другая', 'callback_data' => "payment_wrong_amount_{$payment_id}"]
                        ]
                    ]
                ];

                $url = "https://api.telegram.org/bot{$bot_token}/sendPhoto";
                $post_fields = [
                    'chat_id' => $chat_id,
                    'photo' => new CURLFile($destPath, $file_type, $unique_name),
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                $resp = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $result = json_decode($resp, true);
                curl_close($ch);

                if ($http_code === 200 && isset($result['ok']) && $result['ok']) {
                    $success = true;
                } else {
                    $error = "Telegramga yuborishda xatolik yuz berdi: " . ($result['description'] ?? 'Noma\'lum xatolik');
                    error_log($resp);
                }
            }
        }
    } else {
        $error = "Iltimos, chek va to'g'ri summani kiriting.";
    }
}
?>

<!DOCTYPE html>
<html lang="uz" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title data-i18n="topup_title">Balansni to'ldirish</title>
    <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
    <link rel="apple-touch-icon" href="../../img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="../../img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/profile.css">
    <style>
        .topup-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .topup-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        [data-theme="light"] .topup-card {
            border: 1px solid var(--light-gray);
        }

        .page-title {
            font-family: "Space Grotesk", sans-serif;
            color: var(--text-color);
            margin-bottom: 24px;
            text-align: center;
            font-size: 1.5rem;
        }

        .card-info {
            background-color: var(--secondary-color);
            border-radius: var(--border-radius);
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .card-info:hover {
            transform: translateY(-2px);
        }

        .card-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--accent-color);
            margin: 10px 0;
            word-break: break-all;
        }

        .card-holder {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .copy-feedback {
            opacity: 0;
            transition: opacity 0.3s ease;
            font-size: 0.8rem;
            color: var(--success-color);
            margin-top: 8px;
        }

        .copy-feedback.show {
            opacity: 1;
        }

        .info-box {
            background-color: rgba(46, 204, 113, 0.1);
            border-radius: var(--border-radius);
            padding: 16px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success-color);
        }

        [data-theme="light"] .info-box {
            background-color: rgba(46, 204, 113, 0.15);
        }

        .info-box h3 {
            font-size: 1rem;
            color: var(--text-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box ul {
            margin: 10px 0 0 20px;
            padding-left: 0;
        }

        .info-box li {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .form-group input[type="file"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px;
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 255, 255, 0.1);
            background-color: var(--secondary-color);
            color: var(--text-color);
        }

        [data-theme="light"] .form-group input[type="file"],
        [data-theme="light"] .form-group input[type="number"] {
            border: 1px solid var(--light-gray);
            background-color: var(--white);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: var(--accent-color);
            color: var(--primary-color);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .message {
            text-align: center;
            padding: 12px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .message.success {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .message.error {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .action-links {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .action-link {
            padding: 10px 16px;
            border-radius: var(--border-radius);
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-link.primary {
            background-color: var(--accent-color);
            color: var(--primary-color);
        }

        .action-link.secondary {
            background-color: var(--card-bg);
            color: var(--text-color);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-theme="light"] .action-link.secondary {
            border: 1px solid var(--light-gray);
        }

        .action-link:hover {
            transform: translateY(-1px);
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 255, 255, 0.1);
            background-color: var(--secondary-color);
            color: var(--text-color);
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }

        [data-theme="light"] .form-group textarea {
            border: 1px solid var(--light-gray);
            background-color: var(--white);
        }

        @media (max-width: 480px) {
            .card-number {
                font-size: 1rem;
            }
        }

        i {
            margin: 0 3px;
        }
    </style>
</head>

<body>
    <!-- Навигация -->
    <?php require_once '../../php/navbar.php' ?>

    <div class="container">
        <div class="topup-container">
            <h1 class="page-title" data-i18n="topup_title">Balansni to'ldirish</h1>

            <div class="topup-card">
                <?php if ($success): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i>
                        <span data-i18n="topup_success_msg">So'rovingiz muvaffaqiyatli yuborildi! Tez orada administrator siz bilan bog'lanadi.</span>
                    </div>

                    <div class="action-links">
                        <a href="../payments/" class="action-link primary">
                            <i class="fas fa-receipt"></i> <span data-i18n="payment_history">To'lovlar tarixi</span>
                        </a>
                        <a href="../" class="action-link secondary">
                            <i class="fas fa-arrow-left"></i> <span data-i18n="back_button">Orqaga</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card-info" onclick="copyCardNumber()" title="Karta raqamini nusxalash uchun bosing">
                        <div class="card-number"><?= htmlspecialchars($cardNumber) ?></div>
                        <div class="card-holder"><span data-i18n="card_holder"></span> <?= htmlspecialchars($cardHolderName) ?></div>
                        <div class="copy-feedback"><i class="fas fa-check"></i> <span data-i18n="copied">Nusxalandi!</span></div>
                    </div>

                    <div class="info-box">
                        <h3><i class="fas fa-info-circle"></i> <span data-i18n="attention_title">Diqqat!</span></h3>
                        <p data-i18n="attention_text"></p>
                        <ul>
                            <li data-i18n="receipt_date">Sana (to'lov sanasi)</li>
                            <li data-i18n="receipt_amount">To'langan summa</li>
                            <li data-i18n="receipt_sender">Jo'natuvchi (sizning ism-familiyangiz)</li>
                            <li data-i18n="receipt_receiver">Qabul qiluvchi (karta egasi)</li>
                        </ul>
                        <p data-i18n="receipt_warning"></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="message error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span data-i18n="topup_error"><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="receipt" data-i18n="upload_receipt"><i class="fas fa-receipt"></i> Chekni yuklang (rasm)</label>
                            <input type="file" name="receipt" id="receipt" accept="image/*" required>
                        </div>

                        <div class="form-group">
                            <label for="amount"><i class="fas fa-money-bill-wave"></i><span data-i18n="enter_amount"> Summa (sum)</span></label>
                            <input type="number" name="amount" id="amount" data-i18n-placeholder="amount_placeholder" placeholder="Masalan, 50000" min="1" step="1" required>
                        </div>

                        <div class="form-group">
                            <label for="comment" data-i18n="optional_comment"><i class="fas fa-comment"></i> Izoh (ixtiyoriy)</label>
                            <textarea name="comment" data-i18n-placeholder="comment_placeholder" id="comment" rows="3" placeholder="Qo'shimcha ma'lumot yoki izoh" class="form-control"></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> <span data-i18n="submit_button">Yuborish</span>
                        </button>
                    </form>

                    <div class="action-links">
                        <a href="../" class="action-link secondary">
                            <i class="fas fa-arrow-left"></i> <span data-i18n="back_button">Orqaga</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function copyCardNumber() {
            const number = "9860000000000000";
            navigator.clipboard.writeText(number).then(() => {
                const feedback = document.querySelector(".copy-feedback");

                // Сначала убираем класс, чтобы сбросить эффект (если быстро нажать несколько раз)
                feedback.classList.remove("show");

                // Ждем перерисовку и добавляем класс снова
                setTimeout(() => {
                    feedback.classList.add("show");

                    // Через 2 секунды убираем сообщение
                    setTimeout(() => {
                        feedback.classList.remove("show");
                    }, 2000);
                }, 10);
            });
        }
    </script>
</body>

</html>