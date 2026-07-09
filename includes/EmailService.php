C:\xamp\php\php.exe<?php
/**
 * Shared email delivery helper for reservation notifications.
 * Uses SMTP when configured and falls back to saving a local preview file.
 */

// PHPMailer is optional. We'll attempt to load Composer's autoload or the
// legacy PHPMailer files only when sending via SMTP. If not present,
// the code falls back to saving an email preview file.

function saveEmailToFile($recipient, $subject, $html) {
    $emailDir = __DIR__ . '/../tmp/emails';
    if (!is_dir($emailDir)) {
        mkdir($emailDir, 0777, true);
    }

    $safeSubject = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $subject);
    $filename = $emailDir . '/' . strtolower($safeSubject) . '_' . date('Y-m-d_H-i-s') . '.html';
    $emailContent = "To: $recipient\r\nSubject: $subject\r\n\r\n$html";
    file_put_contents($filename, $emailContent);

    return $filename;
}

function sendHtmlEmail($recipient, $subject, $html, $fromEmail = null, $fromName = null) {
    $fromEmail = $fromEmail ?: SITE_EMAIL;
    $fromName = $fromName ?: SITE_NAME;

    $filename = saveEmailToFile($recipient, $subject, $html);

    if (!defined('SMTP_HOST') || empty(SMTP_HOST) || SMTP_HOST === 'smtp.example.com' || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
        error_log("⚠️ SMTP not configured; saved email preview to: $filename");
        return [
            'success' => false,
            'mode' => 'file',
            'file' => $filename,
            'message' => 'SMTP not configured. Email saved to file.'
        ];
    }

    // Try to load PHPMailer: prefer Composer autoload, else legacy vendor files.
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    } else {
        $legacyBase = __DIR__ . '/../vendor/PHPMailer';
        if (file_exists($legacyBase . '/PHPMailer.php')) {
            require_once $legacyBase . '/PHPMailer.php';
            if (file_exists($legacyBase . '/SMTP.php')) {
                require_once $legacyBase . '/SMTP.php';
            }
            if (file_exists($legacyBase . '/Exception.php')) {
                require_once $legacyBase . '/Exception.php';
            }
        }
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log("❌ PHPMailer not found; cannot send via SMTP. Email saved to file: $filename");
        return [
            'success' => false,
            'mode' => 'file',
            'file' => $filename,
            'message' => 'PHPMailer not installed; saved email to file.'
        ];
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE ?: 'tls';
        $mail->Port = SMTP_PORT ?: 587;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipient);
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);

        $mail->send();
        error_log("✅ SMTP email sent to $recipient");

        return [
            'success' => true,
            'mode' => 'smtp',
            'file' => $filename,
            'message' => 'SMTP send successful.'
        ];
    } catch (\Exception $e) {
        error_log("❌ SMTP delivery failed: " . $e->getMessage());
        return [
            'success' => false,
            'mode' => 'file',
            'file' => $filename,
            'message' => $e->getMessage()
        ];
    }
}
