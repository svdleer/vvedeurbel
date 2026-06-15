<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/http.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$config = app_config();
$botToken = (string) $config['telegram_bot_token'];

if ($botToken === '') {
    json_response(['ok' => false, 'error' => 'Telegram bot niet geconfigureerd'], 501);
}

$url = 'https://api.telegram.org/bot' . $botToken . '/getUpdates';
$result = http_post_json($url, []);

if (!($result['ok'] ?? false)) {
    json_response(['ok' => false, 'error' => 'Kon geen berichten ophalen van Telegram'], 502);
}

$body = $result['body'] ?? '';
$decoded = json_decode($body, true);

if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
    json_response(['ok' => false, 'error' => 'Telegram antwoord ongeldig'], 502);
}

$updates = $decoded['result'] ?? [];
if (empty($updates)) {
    json_response(['ok' => false, 'error' => 'Geen berichten ontvangen. Start eerst een chat met de bot.'], 400);
}

// Pak het meest recente chat ID
$latestUpdate = end($updates);
$chatId = null;

if (isset($latestUpdate['message']['chat']['id'])) {
    $chatId = (int) $latestUpdate['message']['chat']['id'];
} elseif (isset($latestUpdate['callback_query']['from']['id'])) {
    $chatId = (int) $latestUpdate['callback_query']['from']['id'];
}

if (!$chatId) {
    json_response(['ok' => false, 'error' => 'Kon geen chat ID vinden in recente berichten'], 400);
}

json_response(['ok' => true, 'chat_id' => $chatId, 'message' => 'Chat ID gevonden']);
