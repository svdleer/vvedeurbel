<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$config = app_config();
$providedKey = (string) ($_SERVER['HTTP_X_DEVICE_KEY'] ?? '');

if ($config['device_api_key'] === '' || !hash_equals($config['device_api_key'], $providedKey)) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$input = request_json();
$commandId = (int) ($input['command_id'] ?? 0);
$token = trim((string) ($input['token'] ?? ''));

if ($commandId <= 0 || $token === '') {
    json_response(['ok' => false, 'error' => 'command_id en token zijn verplicht'], 422);
}

$pdo = db();
$stmt = $pdo->prepare("UPDATE open_commands SET status = 'acked', acked_at = NOW() WHERE id = :id AND command_token = :token AND status = 'sent'");
$stmt->execute([
    'id' => $commandId,
    'token' => $token,
]);

if ($stmt->rowCount() === 0) {
    json_response(['ok' => false, 'error' => 'Command niet gevonden of al verwerkt'], 404);
}

json_response(['ok' => true]);
