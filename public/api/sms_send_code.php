<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../src/config.php';
    require_once __DIR__ . '/../../src/db.php';
    require_once __DIR__ . '/../../src/notifier.php';
    require_once __DIR__ . '/../../src/phone_number.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?? [];
    $phoneNumber = normalize_phone_number((string) ($input['phone_number'] ?? ''));

    if ($phoneNumber === '' || !is_valid_phone_number($phoneNumber)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => phone_number_validation_message()]);
        exit;
    }

    $config = app_config();
    if (($config['sms_provider'] ?? 'twilio') !== 'twilio') {
        http_response_code(501);
        echo json_encode(['ok' => false, 'error' => 'SMS provider is not configured for Twilio']);
        exit;
    }

    if (($config['twilio_account_sid'] ?? '') === '' || ($config['twilio_auth_token'] ?? '') === '' || ($config['twilio_from'] ?? '') === '') {
        http_response_code(501);
        echo json_encode(['ok' => false, 'error' => 'Twilio configuratie onvolledig']);
        exit;
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + 300);

    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(32) NOT NULL,
        code CHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sv_phone_code (phone_number, code),
        INDEX idx_sv_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->prepare('DELETE FROM sms_verifications WHERE phone_number = :phone_number')
        ->execute(['phone_number' => $phoneNumber]);

    $smsResult = send_sms_twilio($phoneNumber, "Jouw verificatiecode is {$code}. Deze code is 5 minuten geldig.");
    if (!($smsResult['ok'] ?? false)) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'error' => 'SMS fout: ' . (($smsResult['error'] ?? null) ?: 'Onbekende fout'),
            'twilio' => $smsResult['twilio'] ?? null,
        ]);
        exit;
    }

    $pdo->prepare('INSERT INTO sms_verifications (phone_number, code, expires_at) VALUES (:phone_number, :code, :expires_at)')
        ->execute(['phone_number' => $phoneNumber, 'code' => $code, 'expires_at' => $expiresAt]);

    echo json_encode([
        'ok' => true,
        'message' => 'Code verstuurd naar SMS nummer ' . $phoneNumber,
        'phone_number' => $phoneNumber,
        'twilio' => $smsResult['twilio'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
