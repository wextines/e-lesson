<?php
/**
 * Render an email template with variables.
 *
 * @param string $templateName Filename under login/emails/ (without .php)
 * @param array  $vars        Key => value replacements ({{KEY}})
 * @return string Rendered HTML
 */
function renderEmailTemplate(string $templateName, array $vars = []): string
{
    $path = __DIR__ . '/emails/' . $templateName . '.php';
    if (!file_exists($path)) {
        return '';
    }
    $html = file_get_contents($path);
    foreach ($vars as $key => $value) {
        $html = str_replace('{{' . strtoupper($key) . '}}', htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $html);
    }
    return $html;
}

/**
 * Check if SMTP is configured with real credentials.
 */
function isSmtpConfigured(): bool
{
    global $smtp_username, $smtp_password;
    if (empty($smtp_username) || empty($smtp_password)) return false;
    if ($smtp_username === 'your_email@gmail.com') return false;
    if ($smtp_password === 'your_app_password_here') return false;
    return true;
}

/**
 * Send an email via PHPMailer / SMTP.
 *
 * @param string $to        Recipient email
 * @param string $subject   Email subject
 * @param string $htmlBody  HTML body
 * @return bool  True on success
 */
function sendEmail(string $to, string $subject, string $htmlBody): bool
{
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_from_email, $smtp_from_name;

    if (!isSmtpConfigured()) {
        error_log('[AUTH] SMTP not configured — email NOT sent to: ' . $to);
        return false;
    }

    require_once __DIR__ . '/../php/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../php/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/../php/PHPMailer/src/Exception.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_username;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $smtp_port;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;

        $mail->setFrom($smtp_from_email, $smtp_from_name);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[AUTH] SMTP error for ' . $to . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Save verification code to temp file for dev/testing when SMTP is unavailable.
 */
function saveDevCode(string $email, int $code, string $purpose): void
{
    $dir = sys_get_temp_dir() . '/iq-creative-auth';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $data = [
        'email'   => $email,
        'code'    => $code,
        'purpose' => $purpose,
        'time'    => date('Y-m-d H:i:s'),
    ];
    file_put_contents($dir . '/' . md5($email . $purpose) . '.json', json_encode($data));
    error_log("[AUTH-DEV] Verification code for $email ($purpose): $code");
}
