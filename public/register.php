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
        <div style="background: #eaf3ff; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; font-size: 0.92rem; color: #1a4678;">
            <strong>Stap 1:</strong>
            <?php
            require_once __DIR__ . '/../src/config.php';
            $cfg = app_config();
            $botUser = (string) ($cfg['telegram_bot_username'] ?? '');
            if ($botUser !== ''):
            ?>
                Open <a href="https://t.me/<?= htmlspecialchars($botUser); ?>" target="_blank" rel="noopener"><strong>@<?= htmlspecialchars($botUser); ?></strong></a> in Telegram en stuur een bericht (bijv. "hallo").
            <?php else: ?>
                Open de bot van dit gebouw in Telegram en stuur een bericht (bijv. "hallo").
            <?php endif; ?>
            <br>
            <strong>Stap 2:</strong> Klik "Auto-detecteer" &rarr; krijg code in Telegram &rarr; voer code in.
        </div>

        <input type="hidden" name="telegram_chat_id" id="telegram_chat_id_field">

        <!-- Zichtbaar veld dat chat ID toont na verificatie -->
        <input type="text" id="telegram_chat_id_display" placeholder="Wordt ingevuld na verificatie"
               readonly style="background: #f5f5f5; color: #555; cursor: not-allowed; display: none;">

        <div id="telegram-step-detect">
            <button type="button" id="auto-detect-telegram-btn">🔍 Stap 1: Detecteer mijn chat ID</button>
        </div>

        <div id="telegram-step-verify" style="display:none;">
            <p class="muted" style="margin: 0 0 8px;">
                Chat ID <strong id="detected-chat-id-label"></strong> gevonden.
                Er is een 6-cijferige code naar je Telegram gestuurd.
            </p>
            <input type="text" id="telegram-verify-code" placeholder="Voer 6-cijferige code in" maxlength="6" inputmode="numeric" style="letter-spacing: 0.25em; text-align: center;">
            <button type="button" id="verify-telegram-btn" style="margin-top: 8px;">✓ Stap 2: Verifieer code</button>
        </div>

        <div id="telegram-step-done" style="display:none;">
            <div class="flash flash-success" style="margin: 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <span>✓ Chat ID <strong id="verified-chat-id-label"></strong> geverifieerd!</span>
                <button type="button" id="telegram-reset-btn" style="width: auto; padding: 6px 14px; font-size: 0.85rem; background: rgba(0,0,0,0.08); color: inherit;">Opnieuw</button>
            </div>
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
