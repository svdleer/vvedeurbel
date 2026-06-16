<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/house_number.php';
require_once __DIR__ . '/../src/phone_number.php';
require_once __DIR__ . '/../src/notifier.php';

$message = null;
$type = 'info';

$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send_code') {
    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));
    $channel = trim((string) ($_POST['reset_channel'] ?? ''));

    if (!is_valid_house_number($houseNumber)) {
        $message = house_number_validation_message();
        $type = 'error';
    } elseif (!in_array($channel, ['sms', 'telegram'], true)) {
        $message = 'Kies SMS of Telegram.';
        $type = 'error';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT * FROM residents
             WHERE house_number = :house_number
               AND ((:channel = "sms" AND phone_number IS NOT NULL AND phone_number <> "")
                    OR (:channel = "telegram" AND telegram_chat_id IS NOT NULL AND telegram_chat_id <> ""))
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'house_number' => $houseNumber,
            'channel' => $channel,
        ]);
        $resident = $stmt->fetch();

        if (!$resident) {
            $message = 'Geen bewoner met ' . ($channel === 'sms' ? 'telefoonnummer' : 'Telegram chat ID') . ' gevonden voor dit huisnummer.';
            $type = 'error';
        } else {
            $target = $channel === 'sms'
                ? normalize_phone_number((string) $resident['phone_number'])
                : trim((string) $resident['telegram_chat_id']);

            if ($channel === 'sms' && !is_valid_phone_number($target)) {
                $message = 'Geregistreerd telefoonnummer is ongeldig.';
                $type = 'error';
            } elseif ($channel === 'telegram' && $target === '') {
                $message = 'Geregistreerd Telegram chat ID is ongeldig.';
                $type = 'error';
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    resident_id INT NOT NULL,
                    channel VARCHAR(16) NOT NULL,
                    target VARCHAR(64) NOT NULL,
                    code CHAR(6) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pr_resident (resident_id),
                    INDEX idx_pr_lookup (resident_id, channel, code),
                    CONSTRAINT fk_pr_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = gmdate('Y-m-d H:i:s', time() + 600);

                $pdo->prepare('DELETE FROM password_resets WHERE resident_id = :resident_id AND channel = :channel AND used_at IS NULL')
                    ->execute([
                        'resident_id' => $resident['id'],
                        'channel' => $channel,
                    ]);

                $text = 'Je resetcode is ' . $code . '. Deze code is 10 minuten geldig.';
                $send = $channel === 'sms'
                    ? send_sms_twilio($target, $text)
                    : send_telegram_message($target, $text);

                if (!($send['ok'] ?? false)) {
                    $twilioCode = $send['twilio']['code'] ?? null;
                    $extra = $twilioCode ? ' (Twilio code ' . $twilioCode . ')' : '';
                    $message = 'Verzenden mislukt: ' . (($send['error'] ?? 'Onbekende fout')) . $extra;
                    $type = 'error';
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO password_resets (resident_id, channel, target, code, expires_at)
                         VALUES (:resident_id, :channel, :target, :code, :expires_at)'
                    );
                    $insert->execute([
                        'resident_id' => $resident['id'],
                        'channel' => $channel,
                        'target' => $target,
                        'code' => $code,
                        'expires_at' => $expiresAt,
                    ]);

                    $message = 'Code verstuurd via ' . strtoupper($channel) . ' naar ' . $target . '.';
                    $type = 'success';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset_password') {
    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));
    $channel = trim((string) ($_POST['reset_channel'] ?? ''));
    $code = trim((string) ($_POST['code'] ?? ''));
    $newPassword = (string) ($_POST['new_password'] ?? '');

    if (!is_valid_house_number($houseNumber)) {
        $message = house_number_validation_message();
        $type = 'error';
    } elseif (!in_array($channel, ['sms', 'telegram'], true)) {
        $message = 'Kies SMS of Telegram.';
        $type = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Nieuw wachtwoord moet minimaal 8 tekens hebben.';
        $type = 'error';
    } else {
        $code = str_pad((string) preg_replace('/\D/', '', $code), 6, '0', STR_PAD_LEFT);
        $pdo = db();

        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            resident_id INT NOT NULL,
            channel VARCHAR(16) NOT NULL,
            target VARCHAR(64) NOT NULL,
            code CHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pr_resident (resident_id),
            INDEX idx_pr_lookup (resident_id, channel, code),
            CONSTRAINT fk_pr_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare(
            'SELECT pr.id, pr.resident_id
             FROM password_resets pr
             JOIN residents r ON r.id = pr.resident_id
             WHERE r.house_number = :house_number
               AND pr.channel = :channel
               AND pr.code = :code
               AND pr.used_at IS NULL
               AND pr.expires_at > UTC_TIMESTAMP()
             ORDER BY pr.id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'house_number' => $houseNumber,
            'channel' => $channel,
            'code' => $code,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            $message = 'Ongeldige of verlopen code.';
            $type = 'error';
        } else {
            $updateResident = $pdo->prepare('UPDATE residents SET password_hash = :password_hash WHERE id = :id');
            $updateResident->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $row['resident_id'],
            ]);

            $markUsed = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
            $markUsed->execute(['id' => $row['id']]);

            $message = 'Wachtwoord succesvol opnieuw ingesteld. Je kunt nu inloggen.';
            $type = 'success';
        }
    }
}

render_shell_start('Wachtwoord vergeten', 'Reset via SMS of Telegram met verificatiecode.');
echo flash_html($message, $type);
?>

<form method="post" class="form">
    <input type="hidden" name="action" value="send_code">

    <label>Huisnummer
        <input type="number" name="house_number" required min="117" max="156" step="1" placeholder="Bijv. 117">
    </label>

    <label>Kanaal
        <select name="reset_channel" required>
            <option value="sms">SMS</option>
            <option value="telegram">Telegram</option>
        </select>
    </label>

    <button type="submit">Verstuur resetcode</button>
</form>

<form method="post" class="form">
    <input type="hidden" name="action" value="reset_password">

    <label>Huisnummer
        <input type="number" name="house_number" required min="117" max="156" step="1" placeholder="Bijv. 117">
    </label>

    <label>Kanaal
        <select name="reset_channel" required>
            <option value="sms">SMS</option>
            <option value="telegram">Telegram</option>
        </select>
    </label>

    <label>Code
        <input type="text" name="code" required maxlength="6" inputmode="numeric" placeholder="6-cijferige code">
    </label>

    <label>Nieuw wachtwoord
        <input type="password" name="new_password" required minlength="8" placeholder="Minimaal 8 tekens">
    </label>

    <button type="submit">Reset wachtwoord</button>
</form>

<div class="link-row">
    <a href="/login.php">Terug naar login</a>
</div>

<?php
render_shell_end();
