<?php

declare(strict_types=1);

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

load_env_file(dirname(__DIR__) . '/.env');

const NOTIFY_CHANNEL_TELEGRAM = 'telegram';
const NOTIFY_CHANNEL_SMS = 'sms';
const NOTIFY_CHANNEL_PUSH = 'push';

function app_config(): array
{
    return [
        'app_name' => env_value('APP_NAME', 'Zilvervloer Deurbel'),
        'app_url' => rtrim((string) env_value('APP_URL', 'http://localhost'), '/'),
        'db' => [
            'host' => env_value('DB_HOST', '127.0.0.1'),
            'port' => env_value('DB_PORT', '3306'),
            'name' => env_value('DB_NAME', 'deurbel'),
            'user' => env_value('DB_USER', 'root'),
            'pass' => env_value('DB_PASS', ''),
        ],
        'device_api_key' => (string) env_value('DEVICE_API_KEY', ''),
        'door_open_pulse_ms' => (int) env_value('DOOR_OPEN_PULSE_MS', '1200'),
        'telegram_bot_token' => env_value('TELEGRAM_BOT_TOKEN', ''),
        'sms_provider' => env_value('SMS_PROVIDER', 'twilio'),
        'twilio_account_sid' => env_value('TWILIO_ACCOUNT_SID', ''),
        'twilio_auth_token' => env_value('TWILIO_AUTH_TOKEN', ''),
        'twilio_from' => env_value('TWILIO_FROM', ''),
        'push_webhook_secret' => env_value('PUSH_WEBHOOK_SECRET', ''),
    ];
}
