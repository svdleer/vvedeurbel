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

render_shell_start('Bewoner registratie', 'Maximaal 2 accounts per huisnummer.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <div class="grid-2">
        <label>Huisnummer
            <input type="number" name="house_number" required min="117" max="156" step="1" inputmode="numeric" placeholder="Bijv. 117">
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

    <div data-channel="telegram" class="form" style="gap: 10px;">
        <div style="background: #eaf3ff; border-radius: 12px; padding: 12px 14px; font-size: 0.92rem; color: #1a4678;">
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
            <strong>Stap 2:</strong> Klik "Auto-detecteer" → krijg code in Telegram → voer code in.
        </div>

        <input type="hidden" name="telegram_chat_id" id="telegram_chat_id_field">
        <input type="text" id="telegram_chat_id_display" placeholder="Chat ID (wordt automatisch ingevuld na verificatie)"
               readonly style="background: #f5f5f5; color: #555; cursor: not-allowed; display: none;">

        <div id="telegram-step-detect">
            <button type="button" id="auto-detect-telegram-btn">🔍 Stap 1: Detecteer mijn chat ID</button>
        </div>

        <div id="telegram-step-verify" style="display: none; gap: 8px;" class="form">
            <p class="muted" style="margin: 0;">
                Chat ID <strong id="detected-chat-id-label"></strong> gevonden —
                er is een 6-cijferige code naar je Telegram gestuurd.
            </p>
            <input type="text" id="telegram-verify-code" placeholder="6-cijferige code" maxlength="6" inputmode="numeric" style="letter-spacing: 0.3em; text-align: center; font-size: 1.3rem;">
            <button type="button" id="verify-telegram-btn">✓ Stap 2: Verifieer code</button>
        </div>

        <div id="telegram-step-done" style="display: none;">
            <div class="flash flash-success" style="margin: 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <span>✓ Chat ID <strong id="verified-chat-id-label"></strong> geverifieerd!</span>
                <button type="button" id="telegram-reset-btn" style="width: auto; padding: 6px 14px; font-size: 0.85rem; background: rgba(0,0,0,0.08); color: inherit;">Opnieuw</button>
            </div>
        </div>

        <p class="muted" style="font-size: 0.85rem; margin: 0;">
            <a href="/help-telegram-id.php" target="_blank">Hulp nodig?</a>
        </p>
    </div>

    <label data-channel="sms">Mobiel nummer
        <div style="background: #eef8ee; border-radius: 12px; padding: 12px 14px; font-size: 0.92rem; color: #1f5a1f; margin-bottom: 10px;">
            <strong>Stap 1:</strong> Vul je mobiele nummer in in E.164 formaat, bijvoorbeeld <strong>+31612345678</strong>.
            <br>
            <strong>Stap 2:</strong> Klik op <strong>SMS-code verzenden</strong> en voer daarna de ontvangen code in.
        </div>

        <input type="hidden" name="phone_number" id="sms_phone_number_field">
        <input type="text" id="sms_phone_number_display" placeholder="Mobiel nummer (wordt bevestigd)"
               readonly style="background: #f5f5f5; color: #555; cursor: not-allowed; display: none;">

        <div id="sms-step-send">
            <input type="tel" id="sms-phone-input" placeholder="Bijv. +31612345678" inputmode="tel">
            <button type="button" id="sms-send-code-btn">📩 SMS-code verzenden</button>
        </div>

        <div id="sms-step-verify" style="display: none; gap: 8px;" class="form">
            <p class="muted" style="margin: 0;">
                Nummer <strong id="sms-detected-label"></strong> gevonden —
                er is een 6-cijferige code via SMS verstuurd.
            </p>
            <input type="text" id="sms-verify-code" placeholder="6-cijferige code" maxlength="6" inputmode="numeric" style="letter-spacing: 0.3em; text-align: center; font-size: 1.3rem;">
            <button type="button" id="sms-verify-btn">✓ SMS-code verifiëren</button>
        </div>

        <div id="sms-step-done" style="display: none;">
            <div class="flash flash-success" style="margin: 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <span>✓ SMS nummer <strong id="sms-verified-label"></strong> geverifieerd!</span>
                <button type="button" id="sms-reset-btn" style="width: auto; padding: 6px 14px; font-size: 0.85rem; background: rgba(0,0,0,0.08); color: inherit;">Opnieuw</button>
            </div>
        </div>
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
