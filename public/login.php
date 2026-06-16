<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/house_number.php';

$message = null;
$type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!is_valid_house_number($houseNumber)) {
        $message = house_number_validation_message();
        $type = 'error';
    } else {
        $result = login_resident($houseNumber, $password);
        if ($result['ok']) {
            header('Location: /dashboard.php');
            exit;
        }

        $message = $result['message'];
        $type = 'error';
    }
}

render_shell_start('Bewoner login', 'Log in om meldingen en deuropening te beheren.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <label>Huisnummer
        <input type="number" name="house_number" required min="117" max="156" step="1" inputmode="numeric" placeholder="Bijv. 117">
    </label>

    <label>Wachtwoord
        <input type="password" name="password" required>
    </label>

    <button type="submit">Inloggen</button>
</form>

<div class="link-row">
    <a href="/register.php">Nog geen account? Registreer</a>
    <a href="/forgot-password.php">Wachtwoord vergeten</a>
    <a href="/index.php">Terug naar deurbel</a>
</div>
<?php
render_shell_end();
