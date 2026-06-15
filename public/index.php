<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/house_number.php';

$message = null;
$type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../src/db.php';
    require_once __DIR__ . '/../src/http.php';
    require_once __DIR__ . '/../src/notifier.php';

    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));

    if ($houseNumber === '') {
        $message = 'Vul een huisnummer in.';
        $type = 'error';
    } elseif (!is_valid_house_number($houseNumber)) {
        $message = house_number_validation_message();
        $type = 'error';
    } else {
        $pdo = db();

        $residentStmt = $pdo->prepare('SELECT * FROM residents WHERE house_number = :house_number LIMIT 1');
        $residentStmt->execute(['house_number' => $houseNumber]);
        $resident = $residentStmt->fetch();

        $ringStmt = $pdo->prepare('INSERT INTO ring_events (house_number, resident_id, status) VALUES (:house_number, :resident_id, :status)');
        $ringStmt->execute([
            'house_number' => $houseNumber,
            'resident_id' => $resident['id'] ?? null,
            'status' => $resident ? 'pending' : 'unmatched',
        ]);

        if ($resident) {
            $ringId = (int) $pdo->lastInsertId();
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTimeImmutable('+2 minutes'))->format('Y-m-d H:i:s');

            $openLinkStmt = $pdo->prepare('INSERT INTO open_links (ring_event_id, token, expires_at) VALUES (:ring_event_id, :token, :expires_at)');
            $openLinkStmt->execute([
                'ring_event_id' => $ringId,
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);

            $openUrl = base_url() . '/open.php?token=' . urlencode($token);
            $notifyMessage = 'Er staat iemand bij de deur voor huisnummer ' . $houseNumber . '.';
            $notifyResult = notify_resident($resident, $notifyMessage, $openUrl);

            $updateStmt = $pdo->prepare('UPDATE ring_events SET status = :status, notify_error = :notify_error WHERE id = :id');
            $updateStmt->execute([
                'status' => $notifyResult['ok'] ? 'notified' : 'notify_failed',
                'notify_error' => $notifyResult['ok'] ? null : (($notifyResult['error'] ?? null) ?: json_encode($notifyResult)),
                'id' => $ringId,
            ]);
            $message = 'Melding verstuurd naar bewoner van huisnummer ' . $houseNumber . '.';
            $type = 'success';
        } else {
            $message = 'Huisnummer ' . $houseNumber . ' is niet aangemeld.';
            $type = 'error';
        }
    }
}

render_shell_start('Bel de bewoner', 'Scan de QR en vul het huisnummer in.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <label>Huisnummer
        <input type="number" name="house_number" placeholder="Bijv. 117" required min="117" max="156" step="1" inputmode="numeric">
    </label>

    <button type="submit">Aanbellen</button>
</form>

<div class="link-row">
    <a href="/register.php">Bewoner registreren</a>
    <a href="/login.php">Bewoner login</a>
</div>
<?php
render_shell_end();
