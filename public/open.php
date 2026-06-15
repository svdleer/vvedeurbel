<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/config.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$pdo = db();
$message = null;
$type = 'info';

$link = null;
if ($token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM open_links WHERE token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $link = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$link) {
        $message = 'Ongeldige link.';
        $type = 'error';
    } elseif ($link['used_at'] !== null) {
        $message = 'Deze link is al gebruikt.';
        $type = 'error';
    } elseif (strtotime((string) $link['expires_at']) < time()) {
        $message = 'Deze link is verlopen.';
        $type = 'error';
    } else {
        $cfg = app_config();
        $commandToken = bin2hex(random_bytes(32));

        $cmdStmt = $pdo->prepare('INSERT INTO open_commands (ring_event_id, command_token, pulse_ms, status) VALUES (:ring_event_id, :command_token, :pulse_ms, :status)');
        $cmdStmt->execute([
            'ring_event_id' => $link['ring_event_id'],
            'command_token' => $commandToken,
            'pulse_ms' => $cfg['door_open_pulse_ms'],
            'status' => 'queued',
        ]);

        $usedStmt = $pdo->prepare('UPDATE open_links SET used_at = NOW() WHERE id = :id');
        $usedStmt->execute(['id' => $link['id']]);

        $eventStmt = $pdo->prepare('UPDATE ring_events SET status = :status, opened_at = NOW() WHERE id = :id');
        $eventStmt->execute([
            'status' => 'opened',
            'id' => $link['ring_event_id'],
        ]);

        $message = 'Deur-open commando verzonden.';
        $type = 'success';
    }
}

render_shell_start('Deur openen', 'Bevestig om de deur op afstand te openen.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
    <button type="submit">Open nu de deur</button>
</form>

<div class="link-row">
    <a href="/dashboard.php">Naar dashboard</a>
    <a href="/index.php">Naar belpagina</a>
</div>
<?php
render_shell_end();
