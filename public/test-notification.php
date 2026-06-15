<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/http.php';
require_once __DIR__ . '/../src/notifier.php';

$resident = require_resident();

$message = null;
$type = 'info';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testMessage = 'Testmelding voor huisnummer ' . $resident['house_number'] . '.';
    $openUrl = base_url() . '/dashboard.php';
    $result = notify_resident($resident, $testMessage, $openUrl);

    if (!empty($result['ok'])) {
        $message = 'Testmelding verzonden.';
        $type = 'success';
    } else {
        $message = 'Testmelding mislukt.';
        $type = 'error';
    }
}

render_shell_start('Test notificatie', 'Verstuur een test naar het opgeslagen kanaal van deze bewoner.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <p class="muted">
        Kanaal: <strong><?= htmlspecialchars((string) $resident['notification_channel']); ?></strong>
        <?php if ((string) $resident['notification_channel'] === NOTIFY_CHANNEL_TELEGRAM): ?>
            <br>Telegram chat ID: <strong><?= htmlspecialchars((string) $resident['telegram_chat_id']); ?></strong>
        <?php elseif ((string) $resident['notification_channel'] === NOTIFY_CHANNEL_SMS): ?>
            <br>Telefoonnummer: <strong><?= htmlspecialchars((string) $resident['phone_number']); ?></strong>
        <?php elseif ((string) $resident['notification_channel'] === NOTIFY_CHANNEL_PUSH): ?>
            <br>Push endpoint: <strong><?= htmlspecialchars((string) $resident['push_endpoint']); ?></strong>
        <?php endif; ?>
    </p>

    <button type="submit">Verstuur testmelding</button>
</form>

<?php if (is_array($result)): ?>
    <h2>Resultaat</h2>
    <div class="flash flash-info">
        <pre style="white-space: pre-wrap; margin: 0;"><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
    </div>
<?php endif; ?>

<div class="link-row">
    <a href="/dashboard.php">Terug naar dashboard</a>
    <a href="/logout.php">Uitloggen</a>
</div>
<?php
render_shell_end();
