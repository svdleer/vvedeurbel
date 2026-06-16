<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/config.php';

$config = app_config();
$botUsername = (string) ($config['telegram_bot_username'] ?? '');

render_shell_start('Telegram Chat ID vinden', 'Volg deze stappen om je Telegram chat ID op te zoeken.');
?>

<div class="form">
    <h2>Stap 1: Open de Telegram bot</h2>
    <p class="muted">
        <?php if ($botUsername !== ''): ?>
            Klik op de knop hieronder of zoek <strong>@<?= htmlspecialchars($botUsername); ?></strong> in Telegram.
            <br><br>
            <a href="https://t.me/<?= htmlspecialchars($botUsername); ?>" class="btn" target="_blank" rel="noopener">
                🤖 Open @<?= htmlspecialchars($botUsername); ?> in Telegram
            </a>
        <?php else: ?>
            Zoek in Telegram naar de bot van jouw complex/gebouw. 
            Je beheerder zal je de juiste botnaam gegeven hebben.
            Of vraag het aan je beheerder.
        <?php endif; ?>
    </p>

    <h2>Stap 2: Start een chat</h2>
    <p class="muted">
        Als je de bot geopend hebt, druk op <strong>Start</strong> of stuur een bericht.
    </p>

    <h2>Stap 3: Haal je chat ID op</h2>
    <p class="muted">
        Je chat ID vind je via deze URL. Vervang <code>BOT_TOKEN</code> met jouw token en open het in je browser:
    </p>
    <pre style="background: #f5f5f5; padding: 12px; border-radius: 12px; overflow-x: auto; font-size: 0.85rem;">https://api.telegram.org/bot<strong>BOT_TOKEN</strong>/getUpdates</pre>
    <p class="muted">
        Zoek in het resultaat naar <code>"chat":{"id":12345678}</code>. Dat nummer is je chat ID.
    </p>

    <h2>Stap 4: Chat ID invullen</h2>
    <p class="muted">
        Vul het getal (zonder minusteken, alleen het getal) in het registratieformulier in.
    </p>

    <h2>Sneller: Auto-detecteer je chat ID</h2>
    <p class="muted">
        Als je al minstens één bericht naar de bot hebt gestuurd, kun je dit gebruiken:
    </p>
    <button type="button" id="auto-detect-btn" class="btn">🔍 Detecteer mijn chat ID automatisch</button>
    <div id="auto-detect-result" style="margin-top: 16px; display: none;"></div>
</div>

<div class="link-row">
    <a href="/3317">Terug naar registratie</a>
    <a href="/index.php">Terug naar deurbel</a>
</div>

<script>
const autoDetectBtn = document.getElementById('auto-detect-btn');
const autoDetectResult = document.getElementById('auto-detect-result');

if (autoDetectBtn) {
    autoDetectBtn.addEventListener('click', async () => {
        autoDetectBtn.disabled = true;
        autoDetectBtn.textContent = '⏳ Even geduld...';

        try {
            const res = await fetch('/api/telegram_get_id.php');
            const data = await res.json();

            autoDetectResult.style.display = 'block';

            if (data.ok) {
                autoDetectResult.innerHTML = 
                    '<div class="flash flash-success">' +
                    '<strong>Chat ID gevonden: ' + data.chat_id + '</strong><br>' +
                    'Kopieer dit getal en plak het in het registratieformulier.' +
                    '</div>';
            } else {
                autoDetectResult.innerHTML = 
                    '<div class="flash flash-error">' +
                    '<strong>Fout:</strong> ' + (data.error || 'Onbekende fout') +
                    '</div>';
            }
        } catch (err) {
            autoDetectResult.style.display = 'block';
            autoDetectResult.innerHTML = 
                '<div class="flash flash-error">' +
                '<strong>Fout:</strong> ' + err.message +
                '</div>';
        }

        autoDetectBtn.disabled = false;
        autoDetectBtn.textContent = '🔍 Detecteer mijn chat ID';
    });
}
</script>
<?php
render_shell_end();
