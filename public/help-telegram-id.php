<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/config.php';

$config = app_config();
$botToken = (string) ($config['telegram_bot_token'] ?? '');
$botUsername = '';

// Probeer bot username uit token af te leiden (dit is niet zeker, dus vooral informatief).
if ($botToken !== '') {
    // Dit werkt niet rechtstreeks, dus we laten gebruiker naar BotFather sturen.
}

render_shell_start('Telegram Chat ID vinden', 'Volg deze stappen om je Telegram chat ID op te zoeken.');
?>

<div class="form">
    <h2>Stap 1: Start de bot</h2>
    <p class="muted">
        Klik op de knop hieronder om de Telegram bot te openen in Telegram.
    </p>
    <?php if ($botToken !== ''): ?>
        <a href="https://t.me/<?= htmlspecialchars($botToken); ?>" class="btn" target="_blank" rel="noopener">
            🤖 Open Telegram bot
        </a>
    <?php else: ?>
        <p style="background: #fff3cd; padding: 12px; border-radius: 12px; color: #856404;">
            Bot token niet geconfigureerd. Neem contact op met beheer.
        </p>
    <?php endif; ?>

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

    <h2>Snel alternatief</h2>
    <p class="muted">
        Stuur naar BotFather (zoek "@BotFather" in Telegram) het commando <code>/getMyID</code>.
        Dat geeft je je ID direct.
    </p>
</div>

<div class="link-row">
    <a href="/register.php">Terug naar registratie</a>
    <a href="/index.php">Terug naar deurbel</a>
</div>

<?php
render_shell_end();
