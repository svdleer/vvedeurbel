<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/notifier.php';
require_once __DIR__ . '/../../src/house_number.php';
require_once __DIR__ . '/../../src/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = request_json();
$houseNumber = normalize_house_number((string) ($input['house_number'] ?? ''));

if ($houseNumber === '') {
    json_response(['ok' => false, 'error' => 'house_number is verplicht'], 422);
}

if (!is_valid_house_number($houseNumber)) {
    json_response(['ok' => false, 'error' => house_number_validation_message()], 422);
}

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
$ringId = (int) $pdo->lastInsertId();

$notificationStatus = null;
if ($resident) {
    if (!is_resident_available($resident)) {
        $notificationStatus = 'outside_hours';
        $updateStmt = $pdo->prepare('UPDATE ring_events SET status = :status WHERE id = :id');
        $updateStmt->execute([
            'status' => 'outside_hours',
            'id' => $ringId,
        ]);
    } else {
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+2 minutes'))->format('Y-m-d H:i:s');

        $openLinkStmt = $pdo->prepare('INSERT INTO open_links (ring_event_id, token, expires_at) VALUES (:ring_event_id, :token, :expires_at)');
        $openLinkStmt->execute([
            'ring_event_id' => $ringId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        $openUrl = base_url() . '/open.php?token=' . urlencode($token);
        $notifyResult = notify_resident($resident, 'Er staat iemand bij de deur voor huisnummer ' . $houseNumber . '.', $openUrl);

        $updateStmt = $pdo->prepare('UPDATE ring_events SET status = :status, notify_error = :notify_error WHERE id = :id');
        $updateStmt->execute([
            'status' => $notifyResult['ok'] ? 'notified' : 'notify_failed',
            'notify_error' => $notifyResult['ok'] ? null : (($notifyResult['error'] ?? null) ?: json_encode($notifyResult)),
            'id' => $ringId,
        ]);
    }
}

json_response([
    'ok' => true,
    'ring_event_id' => $ringId,
    'registered' => (bool) $resident,
    'available' => $resident && $notificationStatus !== 'outside_hours',
    'message' => !$resident
        ? 'Huisnummer ' . $houseNumber . ' is niet aangemeld.'
        : ($notificationStatus === 'outside_hours'
            ? 'Huisnummer ' . $houseNumber . ' is momenteel niet beschikbaar. Beschikbaarheid: ' . ($resident['notification_start_hour'] ?? 8) . ':00 - ' . ($resident['notification_end_hour'] ?? 22) . ':00'
            : 'Melding verstuurd naar bewoner van huisnummer ' . $houseNumber . '.'),
]);
