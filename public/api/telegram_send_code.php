<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../src/config.php';
    require_once __DIR__ . '/../../src/db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?? [];
    $chatId = trim((string) ($input['chat_id'] ?? ''));

    if ($chatId === '' || !preg_match('/^-?\d+$/', $chatId)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Ongeldig chat ID']);
        exit;
    }

    $config = app_config();
    $botToken = (string) $config['telegram_bot_token'];

    if ($botToken === '') {
        http_response_code(501);
        echo json_encode(['ok' => false, 'error' => 'Telegram bot niet geconfigureerd']);
        exit;
    }

    // Genereer 6-cijferige code
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = (new DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s');

    $pdo = db();

    // Verwijder oude verificaties voor dit chat ID
    $pdo->prepare('DELETE FROM telegram_verifications WHERE chat_id = :chat_id')->execute(['chat_id' => $chatId]);

    // Sla nieuwe op
    $pdo->prepare('INSERT INTO telegram_verifications (chat_id, code, expires_at) VALUES (:chat_id, :code, :expires_at)')
        ->execute(['chat_id' => $chatId, 'code' => $code, 'expires_at' => $expiresAt]);

    // Stuur code via Telegram
    $message = "🔐 Jouw verificatiecode: <b>{$code}</b>\n\nDeze code is 5 minuten geldig.";
    $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML']),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    if ($httpCode !== 200 || !($decoded['ok'] ?? false)) {
        $error = $decoded['description'] ?? 'Onbekende fout';
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Telegram fout: ' . $error]);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'Code verstuurd naar Telegram chat ' . $chatId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
