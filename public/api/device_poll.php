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

$pdo->exec("CREATE TABLE IF NOT EXISTS device_heartbeats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(64) NOT NULL,
    last_seen_at DATETIME NOT NULL,
    last_ip VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_device_name (device_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$deviceName = 'main-doorbell';
$remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$hbStmt = $pdo->prepare(
    'INSERT INTO device_heartbeats (device_name, last_seen_at, last_ip)
     VALUES (:device_name, NOW(), :last_ip)
     ON DUPLICATE KEY UPDATE last_seen_at = NOW(), last_ip = VALUES(last_ip)'
);
$hbStmt->execute([
    'device_name' => $deviceName,
    'last_ip' => $remoteIp !== '' ? $remoteIp : null,
]);

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
