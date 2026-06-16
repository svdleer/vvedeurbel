<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/house_number.php';
require_once __DIR__ . '/../src/phone_number.php';

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

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_resident') {
    $residentId = (int) ($_POST['resident_id'] ?? 0);
    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));
    $channel = trim((string) ($_POST['notification_channel'] ?? ''));
    $telegramChatId = trim((string) ($_POST['telegram_chat_id'] ?? ''));
    $phoneNumber = normalize_phone_number((string) ($_POST['phone_number'] ?? ''));

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
        $pdo = db();
        $stmt = $pdo->prepare(
            'UPDATE residents
             SET house_number = :house_number,
                 notification_channel = :notification_channel,
                 telegram_chat_id = :telegram_chat_id,
                 phone_number = :phone_number
             WHERE id = :id'
        );
        $stmt->execute([
            'house_number' => $houseNumber,
            'notification_channel' => $channel,
            'telegram_chat_id' => $channel === NOTIFY_CHANNEL_TELEGRAM ? $telegramChatId : null,
            'phone_number' => $channel === NOTIFY_CHANNEL_SMS ? $phoneNumber : null,
            'id' => $residentId,
        ]);

        $message = 'Bewoner bijgewerkt.';
        $type = 'success';
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
?>

<form method="post" class="form">
    <input type="hidden" name="action" value="admin_logout">
    <button type="submit">Uitloggen admin</button>
</form>

<h2>Bewoners</h2>
<?php if (empty($residents)): ?>
    <p class="muted">Geen bewoners gevonden.</p>
<?php else: ?>
    <div class="form">
        <?php foreach ($residents as $resident): ?>
            <form method="post" class="form" style="border: 1px solid #ddd; border-radius: 12px; padding: 12px;">
                <input type="hidden" name="resident_id" value="<?= (int) $resident['id']; ?>">
                <input type="hidden" name="action" value="update_resident">

                <strong>#<?= (int) $resident['id']; ?></strong>

                <label>Huisnummer
                    <input type="number" name="house_number" required min="117" max="156" step="1" value="<?= htmlspecialchars((string) $resident['house_number']); ?>">
                </label>

                <label>Kanaal
                    <select name="notification_channel" required>
                        <option value="telegram" <?= (string) $resident['notification_channel'] === 'telegram' ? 'selected' : ''; ?>>Telegram</option>
                        <option value="sms" <?= (string) $resident['notification_channel'] === 'sms' ? 'selected' : ''; ?>>SMS</option>
                    </select>
                </label>

                <label>Telegram chat ID
                    <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars((string) ($resident['telegram_chat_id'] ?? '')); ?>">
                </label>

                <label>Telefoonnummer (+316...)
                    <input type="text" name="phone_number" value="<?= htmlspecialchars((string) ($resident['phone_number'] ?? '')); ?>">
                </label>

                <button type="submit">Opslaan</button>
            </form>

            <form method="post" class="form" onsubmit="return confirm('Weet je zeker dat je deze bewoner wilt verwijderen?');">
                <input type="hidden" name="resident_id" value="<?= (int) $resident['id']; ?>">
                <input type="hidden" name="action" value="delete_resident">
                <button type="submit">Verwijderen</button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
render_shell_end();
