<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/notifier.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = request_json();
$houseNumber = strtoupper(trim((string) ($input['house_number'] ?? '')));

if ($houseNumber === '') {
    json_response(['ok' => false, 'error' => 'house_number is verplicht'], 422);
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

if ($resident) {
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

json_response([
    'ok' => true,
    'ring_event_id' => $ringId,
    'message' => 'Als het huisnummer bekend is, is de melding verstuurd.',
]);
