<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/auth.php';

$message = null;
$type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = register_resident($_POST);
    $message = $result['message'];
    $type = $result['ok'] ? 'success' : 'error';
}

render_shell_start('Bewoner registratie', 'Per huisnummer precies 1 account.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <div class="grid-2">
        <label>Huisnummer
            <input type="text" name="house_number" required maxlength="32" placeholder="Bijv. 12A">
        </label>

        <label>Wachtwoord
            <input type="password" name="password" required minlength="8" placeholder="Minimaal 8 tekens">
        </label>
    </div>

    <label>Berichtgeving
        <select name="notification_channel" required data-channel-select>
            <option value="telegram">Telegram</option>
            <option value="sms">SMS</option>
            <option value="push">Push webhook</option>
        </select>
    </label>

    <label data-channel="telegram">Telegram chat ID
        <div style="display: flex; gap: 8px; align-items: flex-end;">
            <input type="text" name="telegram_chat_id" placeholder="Bijv. 123456789" inputmode="numeric" style="flex: 1;">
            <button type="button" id="auto-detect-telegram-btn" style="padding: 8px 12px; white-space: nowrap;">🔍 Auto-detecteer</button>
        </div>
        <p class="muted" style="margin-top: 6px; font-size: 0.85rem;">
            <a href="/help-telegram-id.php" target="_blank">Hulp nodig?</a>
        </p>
    </label>

    <label data-channel="sms">Mobiel nummer
        <input type="text" name="phone_number" placeholder="Bijv. +31612345678">
    </label>

    <label data-channel="push">Push endpoint URL
        <input type="url" name="push_endpoint" placeholder="Bijv. https://jouwdomein.nl/push-endpoint">
    </label>

    <button type="submit">Registreren</button>
</form>

<div class="link-row">
    <a href="/login.php">Naar login</a>
    <a href="/index.php">Terug naar deurbel</a>
</div>
<?php
render_shell_end();
