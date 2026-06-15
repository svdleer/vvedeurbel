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
    $code = trim((string) ($input['code'] ?? ''));

    if ($chatId === '' || $code === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'chat_id en code zijn verplicht']);
        exit;
    }

    $pdo = db();

    // Maak tabel aan als die nog niet bestaat
    $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id VARCHAR(64) NOT NULL,
        code CHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tv_chat_code (chat_id, code),
        INDEX idx_tv_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Normaliseer code: verwijder spaties, zet leading zeros terug indien nodig
    $code = str_pad(preg_replace('/\D/', '', $code), 6, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare(
        'SELECT id, code, expires_at, verified_at, UTC_TIMESTAMP() as db_now
         FROM telegram_verifications
         WHERE chat_id = :chat_id
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['chat_id' => $chatId]);
    $latestRow = $stmt->fetch();

    // Debug info — verwijder dit zodra verificatie werkt
    $debug = [
        'input_code' => $code,
        'db_code' => $latestRow['code'] ?? null,
        'db_expires_at' => $latestRow['expires_at'] ?? null,
        'db_verified_at' => $latestRow['verified_at'] ?? null,
        'db_now' => $latestRow['db_now'] ?? null,
    ];

    $stmt2 = $pdo->prepare(
        'SELECT id FROM telegram_verifications
         WHERE chat_id = :chat_id AND code = :code AND expires_at > UTC_TIMESTAMP() AND verified_at IS NULL
         LIMIT 1'
    );
    $stmt2->execute(['chat_id' => $chatId, 'code' => $code]);
    $row = $stmt2->fetch();

    if (!$row) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige of verlopen code', 'debug' => $debug]);
        exit;
    }

    // Markeer als geverifieerd
    $pdo->prepare('UPDATE telegram_verifications SET verified_at = NOW() WHERE id = :id')
        ->execute(['id' => $row['id']]);

    echo json_encode(['ok' => true, 'chat_id' => $chatId, 'message' => 'Chat ID geverifieerd']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
