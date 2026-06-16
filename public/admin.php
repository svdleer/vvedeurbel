<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/house_number.php';
require_once __DIR__ . '/../src/phone_number.php';
require_once __DIR__ . '/../src/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$cfg = app_config();
$adminPassword = (string) ($cfg['admin_password'] ?? '');
$isAdmin = (bool) ($_SESSION['is_admin'] ?? false);

$message = null;
$type = 'info';

if ($adminPassword === '') {
    render_shell_start('Admin', 'Beheer bewonersregistraties.');
    echo flash_html('ADMIN_PASSWORD is niet ingesteld in .env.', 'error');
    render_shell_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_login') {
    $inputPass = (string) ($_POST['admin_password'] ?? '');
    if (hash_equals($adminPassword, $inputPass)) {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
        $message = 'Ingelogd als admin.';
        $type = 'success';
    } else {
        $message = 'Onjuist admin wachtwoord.';
        $type = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_logout') {
    $_SESSION['is_admin'] = false;
    $isAdmin = false;
    $message = 'Uitgelogd.';
    $type = 'info';
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_resident') {
    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $channel = trim((string) ($_POST['notification_channel'] ?? ''));
    $telegramChatId = trim((string) ($_POST['telegram_chat_id'] ?? ''));
    $phoneNumber = normalize_phone_number((string) ($_POST['phone_number'] ?? ''));
    $startHour = (int) ($_POST['notification_start_hour'] ?? 8);
    $endHour = (int) ($_POST['notification_end_hour'] ?? 22);

    if (!is_valid_house_number($houseNumber)) {
        $message = house_number_validation_message();
        $type = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Wachtwoord moet minimaal 8 tekens zijn.';
        $type = 'error';
    } elseif (!in_array($channel, [NOTIFY_CHANNEL_TELEGRAM, NOTIFY_CHANNEL_SMS], true)) {
        $message = 'Kanaal moet Telegram of SMS zijn.';
        $type = 'error';
    } elseif ($channel === NOTIFY_CHANNEL_TELEGRAM && $telegramChatId === '') {
        $message = 'Telegram chat ID is verplicht voor Telegram.';
        $type = 'error';
    } elseif ($channel === NOTIFY_CHANNEL_SMS && !is_valid_phone_number($phoneNumber)) {
        $message = phone_number_validation_message();
        $type = 'error';
    } elseif ($startHour < 0 || $startHour > 23 || $endHour < 0 || $endHour > 23 || $startHour >= $endHour) {
        $message = 'Beschikbare uren moeten geldig zijn (van < tot, beide 0-23).';
        $type = 'error';
    } elseif (resident_count_by_house_number($houseNumber) >= 2) {
        $message = 'Er zijn al 2 accounts voor dit huisnummer.';
        $type = 'error';
    } else {
        $pdo = db();
        ensure_notification_hours_column();

        $stmt = $pdo->prepare(
            'INSERT INTO residents (
                house_number,
                password_hash,
                notification_channel,
                telegram_chat_id,
                phone_number,
                push_endpoint,
                notification_start_hour,
                notification_end_hour
            ) VALUES (
                :house_number,
                :password_hash,
                :notification_channel,
                :telegram_chat_id,
                :phone_number,
                :push_endpoint,
                :start_hour,
                :end_hour
            )'
        );
        $stmt->execute([
            'house_number' => $houseNumber,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'notification_channel' => $channel,
            'telegram_chat_id' => $channel === NOTIFY_CHANNEL_TELEGRAM ? $telegramChatId : null,
            'phone_number' => $channel === NOTIFY_CHANNEL_SMS ? $phoneNumber : null,
            'push_endpoint' => null,
            'start_hour' => $startHour,
            'end_hour' => $endHour,
        ]);

        $message = 'Gebruiker toegevoegd.';
        $type = 'success';
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_resident') {
    $residentId = (int) ($_POST['resident_id'] ?? 0);
    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));
    $channel = trim((string) ($_POST['notification_channel'] ?? ''));
    $telegramChatId = trim((string) ($_POST['telegram_chat_id'] ?? ''));
    $phoneNumber = normalize_phone_number((string) ($_POST['phone_number'] ?? ''));
    $startHour = (int) ($_POST['notification_start_hour'] ?? 8);
    $endHour = (int) ($_POST['notification_end_hour'] ?? 22);

    if ($residentId <= 0) {
        $message = 'Ongeldige bewoner.';
        $type = 'error';
    } elseif (!is_valid_house_number($houseNumber)) {
        $message = house_number_validation_message();
        $type = 'error';
    } elseif (!in_array($channel, [NOTIFY_CHANNEL_TELEGRAM, NOTIFY_CHANNEL_SMS], true)) {
        $message = 'Kanaal moet Telegram of SMS zijn.';
        $type = 'error';
    } elseif ($channel === NOTIFY_CHANNEL_TELEGRAM && $telegramChatId === '') {
        $message = 'Telegram chat ID is verplicht voor Telegram.';
        $type = 'error';
    } elseif ($channel === NOTIFY_CHANNEL_SMS && !is_valid_phone_number($phoneNumber)) {
        $message = phone_number_validation_message();
        $type = 'error';
    } else {
        if ($startHour < 0 || $startHour > 23 || $endHour < 0 || $endHour > 23 || $startHour >= $endHour) {
            $message = 'Beschikbare uren moeten geldig zijn (van < tot, beide 0-23).';
            $type = 'error';
        } else {
            $pdo = db();
            ensure_notification_hours_column();
            $stmt = $pdo->prepare(
                'UPDATE residents
                 SET house_number = :house_number,
                     notification_channel = :notification_channel,
                     telegram_chat_id = :telegram_chat_id,
                     phone_number = :phone_number,
                     notification_start_hour = :start_hour,
                     notification_end_hour = :end_hour
                 WHERE id = :id'
            );
            $stmt->execute([
                'house_number' => $houseNumber,
                'notification_channel' => $channel,
                'telegram_chat_id' => $channel === NOTIFY_CHANNEL_TELEGRAM ? $telegramChatId : null,
                'phone_number' => $channel === NOTIFY_CHANNEL_SMS ? $phoneNumber : null,
                'start_hour' => $startHour,
                'end_hour' => $endHour,
                'id' => $residentId,
            ]);

            $message = 'Bewoner bijgewerkt.';
            $type = 'success';
        }
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_resident') {
    $residentId = (int) ($_POST['resident_id'] ?? 0);
    if ($residentId <= 0) {
        $message = 'Ongeldige bewoner.';
        $type = 'error';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM residents WHERE id = :id');
        $stmt->execute(['id' => $residentId]);
        $message = 'Bewoner verwijderd.';
        $type = 'success';
    }
}

render_shell_start('Admin', 'Beheer bewonersregistraties.');
echo flash_html($message, $type);

echo '<style>
    section.card { max-width: 100% !important; width: 95vw !important; }
    main.page { padding: 24px 12px !important; }
</style>';

if (!$isAdmin):
?>
<form method="post" class="form">
    <input type="hidden" name="action" value="admin_login">
    <label>Admin wachtwoord
        <input type="password" name="admin_password" required>
    </label>
    <button type="submit">Inloggen</button>
</form>
<?php
    render_shell_end();
    exit;
endif;

$residents = db()->query('SELECT * FROM residents ORDER BY house_number ASC, id DESC')->fetchAll();

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS device_heartbeats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(64) NOT NULL,
    last_seen_at DATETIME NOT NULL,
    last_ip VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_device_name (device_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$arduinoStmt = $pdo->prepare('SELECT device_name, last_seen_at, last_ip FROM device_heartbeats WHERE device_name = :device_name LIMIT 1');
$arduinoStmt->execute(['device_name' => 'main-doorbell']);
$arduino = $arduinoStmt->fetch();

$arduinoOnline = false;
$arduinoLastSeenText = 'nooit';
$arduinoIp = '-';
if ($arduino) {
    $lastSeenTs = strtotime((string) $arduino['last_seen_at']);
    $arduinoOnline = $lastSeenTs !== false && (time() - $lastSeenTs) <= 60;
    $arduinoLastSeenText = (string) $arduino['last_seen_at'];
    $arduinoIp = (string) ($arduino['last_ip'] ?? '-') ?: '-';
}
?>

<form method="post" class="form">
    <input type="hidden" name="action" value="admin_logout">
    <button type="submit">Uitloggen admin</button>
</form>

<h2>Arduino status</h2>
<div class="form" style="border: 1px solid #ddd; border-radius: 12px; padding: 12px; gap: 6px;">
    <div><strong>Status:</strong> <?= $arduinoOnline ? 'Online' : 'Offline'; ?></div>
    <div><strong>Laatst gezien:</strong> <?= htmlspecialchars($arduinoLastSeenText); ?></div>
    <div><strong>Laatste IP:</strong> <?= htmlspecialchars($arduinoIp); ?></div>
    <div class="muted" style="font-size: 0.85rem;">Status is online als heartbeat binnen 60 seconden is ontvangen.</div>
</div>

<h2>Gebruiker toevoegen</h2>
<form method="post" class="form" style="border: 1px solid #ddd; border-radius: 12px; padding: 12px;">
    <input type="hidden" name="action" value="add_resident">

    <div class="grid-2">
        <label>Huisnummer
            <input type="number" name="house_number" required min="117" max="156" step="1" placeholder="Bijv. 117">
        </label>

        <label>Wachtwoord
            <input type="password" name="password" required minlength="8" placeholder="Minimaal 8 tekens">
        </label>
    </div>

    <div class="grid-2">
        <label>Kanaal
            <select name="notification_channel" required>
                <option value="sms" selected>SMS</option>
                <option value="telegram">Telegram</option>
            </select>
        </label>

        <label>Telefoonnummer (+316...)
            <input type="text" name="phone_number" placeholder="+31612345678">
        </label>
    </div>

    <label>Telegram chat ID (alleen invullen bij kanaal Telegram)
        <input type="text" name="telegram_chat_id" placeholder="Bijv. 123456789">
    </label>

    <div class="grid-2">
        <label>Beschikbaar van (uur)
            <input type="number" name="notification_start_hour" min="0" max="23" step="1" value="8">
        </label>

        <label>Beschikbaar tot (uur)
            <input type="number" name="notification_end_hour" min="0" max="23" step="1" value="22">
        </label>
    </div>

    <button type="submit">Gebruiker toevoegen</button>
</form>

<h2>Bewoners</h2>
<?php if (empty($residents)): ?>
    <p class="muted">Geen bewoners gevonden.</p>
<?php else: ?>
    <div class="form" style="overflow-x: auto; max-width: 100%; width: 100%;">
        <div class="muted" style="display:grid; grid-template-columns: 60px 100px 110px 200px 200px 80px 80px 100px 100px; gap: 8px; font-size: 0.75rem; min-width: 1100px;">
            <span>ID</span>
            <span>Huisnr</span>
            <span>Kanaal</span>
            <span>Telegram chat ID</span>
            <span>Telefoon</span>
            <span>Van uur</span>
            <span>Tot uur</span>
            <span>Opslaan</span>
            <span>Verwijder</span>
        </div>
        <?php foreach ($residents as $resident): ?>
            <form method="post" class="form" style="border: 1px solid #ddd; border-radius: 12px; padding: 10px; display:grid; grid-template-columns: 60px 100px 110px 200px 200px 80px 80px 100px 100px; gap: 8px; align-items: center; min-width: 1100px;">
                <input type="hidden" name="resident_id" value="<?= (int) $resident['id']; ?>">
                <strong style="font-size: 0.9rem;">#<?= (int) $resident['id']; ?></strong>

                <input type="number" name="house_number" required min="117" max="156" step="1" style="font-size: 0.9rem;" value="<?= htmlspecialchars((string) $resident['house_number']); ?>">

                <select name="notification_channel" required style="font-size: 0.9rem;">
                    <option value="telegram" <?= (string) $resident['notification_channel'] === 'telegram' ? 'selected' : ''; ?>>Telegram</option>
                    <option value="sms" <?= (string) $resident['notification_channel'] === 'sms' ? 'selected' : ''; ?>>SMS</option>
                </select>

                <input type="text" name="telegram_chat_id" style="font-size: 0.9rem;" value="<?= htmlspecialchars((string) ($resident['telegram_chat_id'] ?? '')); ?>" placeholder="Chat ID">

                <input type="text" name="phone_number" style="font-size: 0.9rem;" value="<?= htmlspecialchars((string) ($resident['phone_number'] ?? '')); ?>" placeholder="+316...">

                <input type="number" name="notification_start_hour" min="0" max="23" step="1" style="font-size: 0.9rem;" value="<?= (int) ($resident['notification_start_hour'] ?? 8); ?>">

                <input type="number" name="notification_end_hour" min="0" max="23" step="1" style="font-size: 0.9rem;" value="<?= (int) ($resident['notification_end_hour'] ?? 22); ?>">

                <button type="submit" name="action" value="update_resident" style="font-size: 0.9rem;">Opslaan</button>
                <button type="submit" name="action" value="delete_resident" formnovalidate style="font-size: 0.9rem;" onclick="return confirm('Weet je zeker dat je deze bewoner wilt verwijderen?');">Verwijderen</button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
render_shell_end();
