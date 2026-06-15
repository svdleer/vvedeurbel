<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function http_post_json(string $url, array $payload, array $headers = []): array
{
    $ch = curl_init($url);
    $allHeaders = array_merge(['Content-Type: application/json'], $headers);

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
        return ['ok' => false, 'error' => $error !== '' ? $error : 'Onbekende cURL fout'];
    }

    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'status' => $httpCode, 'body' => $response];
}

function send_telegram_message(string $chatId, string $message): array
{
    $config = app_config();
    $token = $config['telegram_bot_token'];
    if ($token === '') {
        return ['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN ontbreekt'];
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    return http_post_json($url, [
        'chat_id' => $chatId,
        'text' => $message,
        'disable_web_page_preview' => true,
    ]);
}

function send_sms_twilio(string $to, string $message): array
{
    $config = app_config();
    $sid = $config['twilio_account_sid'];
    $token = $config['twilio_auth_token'];
    $from = $config['twilio_from'];

    if ($sid === '' || $token === '' || $from === '') {
        return ['ok' => false, 'error' => 'Twilio configuratie onvolledig'];
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_POSTFIELDS => http_build_query([
            'From' => $from,
            'To' => $to,
            'Body' => $message,
        ]),
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => $error !== '' ? $error : 'Onbekende Twilio fout'];
    }

    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'status' => $httpCode, 'body' => $response];
}

function send_push_webhook(string $endpoint, string $message, string $openUrl): array
{
    $config = app_config();
    $secret = (string) $config['push_webhook_secret'];

    $headers = [];
    if ($secret !== '') {
        $headers[] = 'X-Push-Secret: ' . $secret;
    }

    return http_post_json($endpoint, [
        'title' => 'Deurbel melding',
        'message' => $message,
        'open_url' => $openUrl,
    ], $headers);
}

function notify_resident(array $resident, string $message, string $openUrl): array
{
    $channel = $resident['notification_channel'];

    if ($channel === NOTIFY_CHANNEL_TELEGRAM) {
        return send_telegram_message((string) $resident['telegram_chat_id'], $message . "\n" . $openUrl);
    }

    if ($channel === NOTIFY_CHANNEL_SMS) {
        return send_sms_twilio((string) $resident['phone_number'], $message . ' ' . $openUrl);
    }

    if ($channel === NOTIFY_CHANNEL_PUSH) {
        return send_push_webhook((string) $resident['push_endpoint'], $message, $openUrl);
    }

    return ['ok' => false, 'error' => 'Onbekend notificatiekanaal'];
}
