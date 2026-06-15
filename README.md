# Tijdelijk Deurbel Systeem (LAMP + Arduino)

Snel inzetbaar deurbel-systeem voor appartementen:

- QR bij ingang -> webpagina met huisnummer invoer
- Bewoner ontvangt melding via Telegram, SMS of push-webhook
- Bewoner opent deur via beveiligde link
- Arduino op 4G/WiFi pollt server en activeert relais voor deuropener

## Aanbeveling notificatiekanaal

Voor de snelste en goedkoopste livegang: **Telegram**.

- Geen SMS-kosten per bericht
- Zeer snelle aflevering
- Eenvoudige integratie via bot token + chat ID
- Werkt stabiel zonder app-store publicatie

SMS (Twilio) is een goede fallback, maar duurder en meer configuratie.
Push kan via webhook, maar vereist extra app/infrastructuur.

## Structuur

- `public/` website en API endpoints
- `src/` PHP core (db/auth/notifier/view)
- `migrations/` SQL schema
- `arduino/` microcontroller client
- `scripts/` helper scripts

## Vereisten

- PHP 8.1+
- MySQL/MariaDB
- Apache met mod_rewrite
- PHP cURL extensie
- Arduino ESP32/ESP8266 + relaismodule

## Installatie op Plesk/LAMP

1. Clone upload naar webroot (of subdomain root).
2. Kopieer `.env.example` naar `.env` en vul waarden in.
3. Maak database en user aan.
4. Voer SQL uit:

```bash
mysql -h 127.0.0.1 -P 3306 -u deurbel_user -p deurbel < migrations/001_init.sql
```

of met helper script:

```bash
bash scripts/run_migration.sh 127.0.0.1 3306 deurbel deurbel_user 'jouwWachtwoord'
```

5. Zet document root op `public/`.
6. Zorg dat HTTPS actief is.
7. QR code laten verwijzen naar: `https://jouwdomein.nl/index.php`

## Telegram setup

1. Maak bot via BotFather.
2. Zet `TELEGRAM_BOT_TOKEN` in `.env`.
3. Start chat met je bot.
4. Bepaal chat ID (via getUpdates of helper-bot).
5. Bewoner kiest Telegram bij registratie en vult chat ID in.

## Bel-flow

1. Bezoeker scant QR en vult huisnummer.
2. Systeem maakt `ring_event`.
3. Bewoner krijgt melding met korte open-link (2 min geldig).
4. Bewoner klikt op link en bevestigt openen.
5. Backend zet open-command in queue.
6. Arduino pollt `/api/device_poll.php` en ontvangt command.
7. Arduino pulst relais en ackt via `/api/device_ack.php`.

## Security quick wins

- Gebruik sterke `DEVICE_API_KEY` en lange random waarden.
- Laat API alleen over HTTPS lopen.
- Beperk polling endpoint via firewall/IP allowlist indien mogelijk.
- Gebruik sterke wachtwoorden per bewoner.
- Zet server logs + fail2ban/rate limiting aan in Plesk.

## Arduino

Zie `arduino/deurbel_client.ino`.

Pas aan:

- WiFi SSID/password
- `API_BASE`
- `DEVICE_KEY`
- Relais pin

## Nog toe te voegen (optioneel)

- Rate limiting op `ring.php`
- Audit dashboard voor beheerder
- Tijdvenster waarop deur geopend mag worden
- Camera snapshot koppeling
