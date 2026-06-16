<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/house_number.php';
require_once __DIR__ . '/phone_number.php';

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function resident_by_house_number(string $houseNumber): ?array
{
    $stmt = db()->prepare('SELECT * FROM residents WHERE house_number = :house_number LIMIT 1');
    $stmt->execute(['house_number' => $houseNumber]);
    $resident = $stmt->fetch();

    return $resident ?: null;
}

function resident_count_by_house_number(string $houseNumber): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS cnt FROM residents WHERE house_number = :house_number');
    $stmt->execute(['house_number' => $houseNumber]);
    $row = $stmt->fetch();

    return (int) ($row['cnt'] ?? 0);
}

function residents_by_house_number(string $houseNumber): array
{
    $stmt = db()->prepare('SELECT * FROM residents WHERE house_number = :house_number ORDER BY id DESC');
    $stmt->execute(['house_number' => $houseNumber]);

    return $stmt->fetchAll() ?: [];
}

function ensure_notification_hours_column(): void
{
    $pdo = db();
    try {
        $pdo->exec("ALTER TABLE residents ADD COLUMN notification_start_hour TINYINT UNSIGNED DEFAULT 8");
    } catch (Exception) {
        // Column already exists
    }
    try {
        $pdo->exec("ALTER TABLE residents ADD COLUMN notification_end_hour TINYINT UNSIGNED DEFAULT 22");
    } catch (Exception) {
        // Column already exists
    }
}

function is_resident_available(array $resident): bool
{
    ensure_notification_hours_column();
    
    $startHour = (int) ($resident['notification_start_hour'] ?? 8);
    $endHour = (int) ($resident['notification_end_hour'] ?? 22);
    $currentHour = (int) (new DateTime('now', new DateTimeZone('Europe/Amsterdam')))->format('H');
    
    return $currentHour >= $startHour && $currentHour < $endHour;
}

function is_sms_verified_recently(string $phoneNumber): bool
{
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
         WHERE phone_number = :phone_number
           AND verified_at IS NOT NULL
           AND verified_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['phone_number' => $phoneNumber]);

    return (bool) $stmt->fetch();
}

function register_resident(array $input): array
{
    $houseNumber = normalize_house_number((string) ($input['house_number'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $channel = trim($input['notification_channel'] ?? '');

    if ($houseNumber === '' || $password === '') {
        return ['ok' => false, 'message' => 'Huisnummer en wachtwoord zijn verplicht.'];
    }

    if (!is_valid_house_number($houseNumber)) {
        return ['ok' => false, 'message' => house_number_validation_message()];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Wachtwoord moet minimaal 8 tekens zijn.'];
    }

    if (!in_array($channel, [NOTIFY_CHANNEL_TELEGRAM, NOTIFY_CHANNEL_SMS], true)) {
        return ['ok' => false, 'message' => 'Kies een geldig notificatiekanaal.'];
    }

    $telegramChatId = trim((string) ($input['telegram_chat_id'] ?? ''));
    $phoneNumber = normalize_phone_number((string) ($input['phone_number'] ?? ''));
    $pushEndpoint = trim((string) ($input['push_endpoint'] ?? ''));

    if ($channel === NOTIFY_CHANNEL_TELEGRAM && $telegramChatId === '') {
        return ['ok' => false, 'message' => 'Telegram chat ID is verplicht voor Telegram notificaties.'];
    }

    if ($channel === NOTIFY_CHANNEL_SMS && $phoneNumber === '') {
        return ['ok' => false, 'message' => 'Telefoonnummer is verplicht voor SMS notificaties.'];
    }

    if ($channel === NOTIFY_CHANNEL_SMS && !is_valid_phone_number($phoneNumber)) {
        return ['ok' => false, 'message' => phone_number_validation_message()];
    }

    if ($channel === NOTIFY_CHANNEL_SMS && !is_sms_verified_recently($phoneNumber)) {
        return ['ok' => false, 'message' => 'Verifieer eerst je SMS nummer met een code.'];
    }

    if (resident_count_by_house_number($houseNumber) >= 2) {
        return ['ok' => false, 'message' => 'Er zijn al 2 accounts voor dit huisnummer.'];
    }

    $startHour = (int) ($input['notification_start_hour'] ?? 8);
    $endHour = (int) ($input['notification_end_hour'] ?? 22);
    if ($startHour < 0 || $startHour > 23 || $endHour < 0 || $endHour > 23 || $startHour >= $endHour) {
        $startHour = 8;
        $endHour = 22;
    }

    ensure_notification_hours_column();
    $stmt = db()->prepare('INSERT INTO residents (house_number, password_hash, notification_channel, telegram_chat_id, phone_number, push_endpoint, notification_start_hour, notification_end_hour) VALUES (:house_number, :password_hash, :notification_channel, :telegram_chat_id, :phone_number, :push_endpoint, :start_hour, :end_hour)');
    $stmt->execute([
        'house_number' => $houseNumber,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'notification_channel' => $channel,
        'telegram_chat_id' => $telegramChatId !== '' ? $telegramChatId : null,
        'phone_number' => $phoneNumber !== '' ? $phoneNumber : null,
        'push_endpoint' => $pushEndpoint !== '' ? $pushEndpoint : null,
        'start_hour' => $startHour,
        'end_hour' => $endHour,
    ]);

    return ['ok' => true, 'message' => 'Registratie gelukt. Je kunt nu inloggen.'];
}

function login_resident(string $houseNumber, string $password): array
{
    $houseNumber = normalize_house_number($houseNumber);
    if (!is_valid_house_number($houseNumber)) {
        return ['ok' => false, 'message' => house_number_validation_message()];
    }

    $residents = residents_by_house_number($houseNumber);
    if (empty($residents)) {
        return ['ok' => false, 'message' => 'Onjuiste inloggegevens.'];
    }

    $resident = null;
    foreach ($residents as $candidate) {
        if (password_verify($password, (string) $candidate['password_hash'])) {
            $resident = $candidate;
            break;
        }
    }

    if ($resident === null) {
        return ['ok' => false, 'message' => 'Onjuiste inloggegevens.'];
    }

    ensure_session_started();
    $_SESSION['resident_id'] = (int) $resident['id'];

    return ['ok' => true, 'resident' => $resident];
}

function current_resident(): ?array
{
    ensure_session_started();
    $residentId = (int) ($_SESSION['resident_id'] ?? 0);
    if ($residentId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM residents WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $residentId]);

    $resident = $stmt->fetch();
    return $resident ?: null;
}

function require_resident(): array
{
    $resident = current_resident();
    if ($resident === null) {
        header('Location: /login.php');
        exit;
    }

    return $resident;
}

function logout_resident(): void
{
    ensure_session_started();
    $_SESSION = [];
    session_destroy();
}
