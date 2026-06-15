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

    $stmt = $pdo->prepare(
        'SELECT id FROM telegram_verifications
         WHERE chat_id = :chat_id AND code = :code AND expires_at > NOW() AND verified_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['chat_id' => $chatId, 'code' => $code]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige of verlopen code']);
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
