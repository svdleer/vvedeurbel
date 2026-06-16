<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../src/db.php';
    require_once __DIR__ . '/../../src/phone_number.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?? [];
    $phoneNumber = normalize_phone_number((string) ($input['phone_number'] ?? ''));
    $code = trim((string) ($input['code'] ?? ''));

    if ($phoneNumber === '' || !is_valid_phone_number($phoneNumber)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => phone_number_validation_message()]);
        exit;
    }

    $code = preg_replace('/\D/', '', $code) ?? '';
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);

    if ($code === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'code is verplicht']);
        exit;
    }

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

    $stmt = $pdo->prepare(
        'SELECT id FROM sms_verifications
         WHERE phone_number = :phone_number AND code = :code AND expires_at > UTC_TIMESTAMP() AND verified_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['phone_number' => $phoneNumber, 'code' => $code]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige of verlopen code']);
        exit;
    }

    $pdo->prepare('UPDATE sms_verifications SET verified_at = NOW() WHERE id = :id')
        ->execute(['id' => $row['id']]);

    echo json_encode(['ok' => true, 'phone_number' => $phoneNumber, 'message' => 'SMS nummer geverifieerd']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
