<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

$resident = require_resident();
$pdo = db();

$message = null;
$type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'open_latest') {
    $stmt = $pdo->prepare(
        "SELECT ol.* FROM open_links ol
         JOIN ring_events re ON re.id = ol.ring_event_id
         WHERE re.resident_id = :resident_id
           AND ol.used_at IS NULL
           AND ol.expires_at > NOW()
         ORDER BY ol.id DESC
         LIMIT 1"
    );
    $stmt->execute(['resident_id' => $resident['id']]);
    $link = $stmt->fetch();

    if (!$link) {
        $message = 'Geen actieve deurbelmelding gevonden.';
        $type = 'error';
    } else {
        header('Location: /open.php?token=' . urlencode((string) $link['token']));
        exit;
    }
}

$eventsStmt = $pdo->prepare(
    "SELECT id, house_number, status, created_at, opened_at
     FROM ring_events
     WHERE resident_id = :resident_id
     ORDER BY id DESC
     LIMIT 10"
);
$eventsStmt->execute(['resident_id' => $resident['id']]);
$events = $eventsStmt->fetchAll();

render_shell_start('Welkom huisnummer ' . $resident['house_number'], 'Laatste belmomenten en snelle deuropening.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <input type="hidden" name="action" value="open_latest">
    <button type="submit">Open deur voor laatste melding</button>
</form>

<h2>Laatste meldingen</h2>
<?php if (empty($events)): ?>
    <p class="muted">Nog geen meldingen ontvangen.</p>
<?php else: ?>
    <div class="form">
        <?php foreach ($events as $event): ?>
            <div class="flash flash-info">
                <strong>#<?= (int) $event['id']; ?></strong>
                - status: <?= htmlspecialchars((string) $event['status']); ?>
                - tijd: <?= htmlspecialchars((string) $event['created_at']); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="link-row">
    <a href="/index.php">Naar belpagina</a>
    <a href="/logout.php">Uitloggen</a>
</div>
<?php
render_shell_end();
