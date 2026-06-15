<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = request_json();
$token = trim((string) ($input['token'] ?? ''));

if ($token === '') {
    json_response(['ok' => false, 'error' => 'token is verplicht'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM open_links WHERE token = :token LIMIT 1');
$stmt->execute(['token' => $token]);
$link = $stmt->fetch();

if (!$link) {
    json_response(['ok' => false, 'error' => 'Ongeldige token'], 404);
}

if ($link['used_at'] !== null) {
    json_response(['ok' => false, 'error' => 'Token al gebruikt'], 409);
}

if (strtotime((string) $link['expires_at']) < time()) {
    json_response(['ok' => false, 'error' => 'Token verlopen'], 410);
}

$config = app_config();
$cmdStmt = $pdo->prepare('INSERT INTO open_commands (ring_event_id, command_token, pulse_ms, status) VALUES (:ring_event_id, :command_token, :pulse_ms, :status)');
$cmdStmt->execute([
    'ring_event_id' => $link['ring_event_id'],
    'command_token' => bin2hex(random_bytes(32)),
    'pulse_ms' => $config['door_open_pulse_ms'],
    'status' => 'queued',
]);

$usedStmt = $pdo->prepare('UPDATE open_links SET used_at = NOW() WHERE id = :id');
$usedStmt->execute(['id' => $link['id']]);

$eventStmt = $pdo->prepare('UPDATE ring_events SET status = :status, opened_at = NOW() WHERE id = :id');
$eventStmt->execute([
    'status' => 'opened',
    'id' => $link['ring_event_id'],
]);

json_response(['ok' => true, 'message' => 'Open commando in wachtrij gezet']);
