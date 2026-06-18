<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/house_number.php';
require_once __DIR__ . '/../src/door_actions.php';

$houseNumber = normalize_house_number((string) ($_GET['house_number'] ?? $_POST['house_number'] ?? ''));
if ($houseNumber === '' || !is_valid_house_number($houseNumber)) {
    render_shell_start('Deur openen', 'Directe open-link.');
    echo flash_html($houseNumber === '' ? 'Geen huisnummer opgegeven.' : house_number_validation_message(), 'error');
    echo '<div class="link-row"><a href="/index.php">Terug naar belpagina</a></div>';
    render_shell_end();
    exit;
}

$resident = current_resident();
if ($resident === null) {
    header('Location: /login.php?next=' . urlencode('/openen/' . $houseNumber));
    exit;
}

if ((string) $resident['house_number'] !== $houseNumber) {
    render_shell_start('Deur openen', 'Directe open-link.');
    echo flash_html('Je bent ingelogd voor huisnummer ' . $resident['house_number'] . ' en mag dit huisnummer niet openen.', 'error');
    echo '<div class="link-row"><a href="/dashboard.php">Naar dashboard</a><a href="/logout.php">Uitloggen</a></div>';
    render_shell_end();
    exit;
}

$message = null;
$type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = queue_direct_open_for_resident($resident);
    $message = (string) ($result['message'] ?? 'Open commando verzonden.');
    $type = !empty($result['ok']) ? 'success' : 'error';
}

render_shell_start('Deur openen huisnummer ' . $houseNumber, 'Directe open-link voor geauthenticeerde bewoner.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <input type="hidden" name="house_number" value="<?= htmlspecialchars($houseNumber); ?>">
    <button type="submit">Open nu de deur</button>
</form>

<div class="link-row">
    <a href="/dashboard.php">Naar dashboard</a>
    <a href="/logout.php">Uitloggen</a>
</div>
<?php
render_shell_end();
