<?php
require_once '../config/config.php';
require_once '../includes/EmailService.php';

$sent = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['to'])) {
    $to = filter_var($_POST['to'], FILTER_VALIDATE_EMAIL);
    if ($to) {
        $html = '<h2>SMTP Test Email</h2><p>This is a test message sent by Villa Soledad Garden Resort.</p><p>Timestamp: ' . date('Y-m-d H:i:s') . '</p>';
        $result = sendHtmlEmail($to, 'SMTP Test - Villa Soledad Garden Resort', $html, SITE_EMAIL, SITE_NAME);
        $sent = $result['success'];
        $message = $result['message'];
    } else {
        $message = 'Please enter a valid email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .card { max-width: 600px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: green; }
        .warning { color: #b8860b; }
        input, button { padding: 8px; margin-top: 10px; width: 100%; }
    </style>
</head>
<body>
    <div class="card">
        <h2>SMTP Test</h2>
        <p>Current SMTP host: <strong><?php echo htmlspecialchars(SMTP_HOST); ?></strong></p>
        <p>Current SMTP username: <strong><?php echo htmlspecialchars(SMTP_USERNAME ?: 'not configured'); ?></strong></p>
        <form method="post">
            <input type="email" name="to" placeholder="Recipient email" required>
            <button type="submit">Send Test Email</button>
        </form>
        <?php if ($message): ?>
            <p class="<?php echo $sent ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
