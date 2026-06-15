<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$config = app_config();
$providedKey = (string) ($_SERVER['HTTP_X_DEVICE_KEY'] ?? '');

if ($config['device_api_key'] === '' || !hash_equals($config['device_api_key'], $providedKey)) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$pdo = db();
$stmt = $pdo->query("SELECT id, command_token, pulse_ms FROM open_commands WHERE status = 'queued' ORDER BY id ASC LIMIT 1");
$command = $stmt->fetch();

if (!$command) {
    json_response(['ok' => true, 'has_command' => false]);
}

$updateStmt = $pdo->prepare("UPDATE open_commands SET status = 'sent', sent_at = NOW() WHERE id = :id AND status = 'queued'");
$updateStmt->execute(['id' => $command['id']]);

if ($updateStmt->rowCount() === 0) {
    json_response(['ok' => true, 'has_command' => false]);
}

json_response([
    'ok' => true,
    'has_command' => true,
    'command' => [
        'id' => (int) $command['id'],
        'token' => $command['command_token'],
        'pulse_ms' => (int) $command['pulse_ms'],
    ],
]);
