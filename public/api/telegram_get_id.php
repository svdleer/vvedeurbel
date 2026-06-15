<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function http_post_json_inline(string $url, array $payload): array
{
    $ch = curl_init($url);
    $allHeaders = ['Content-Type: application/json'];

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => $error !== '' ? $error : 'cURL error'];
    }

    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'status' => $httpCode, 'body' => $response];
}

try {
    require_once __DIR__ . '/../../src/config.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $config = app_config();
    $botToken = (string) $config['telegram_bot_token'];

    if ($botToken === '') {
        http_response_code(501);
        echo json_encode(['ok' => false, 'error' => 'Telegram bot niet geconfigureerd']);
        exit;
    }

    $url = 'https://api.telegram.org/bot' . $botToken . '/getUpdates';
    $result = http_post_json_inline($url, []);

    if (!($result['ok'] ?? false)) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Kon geen berichten ophalen van Telegram']);
        exit;
    }

    $body = $result['body'] ?? '';
    $decoded = json_decode($body, true);

    if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Telegram antwoord ongeldig']);
        exit;
    }

    $updates = $decoded['result'] ?? [];
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Geen berichten ontvangen. Start eerst een chat met de bot.']);
        exit;
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
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Kon geen chat ID vinden in recente berichten']);
        exit;
    }

    echo json_encode(['ok' => true, 'chat_id' => $chatId, 'message' => 'Chat ID gevonden']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
