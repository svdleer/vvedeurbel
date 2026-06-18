<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/house_number.php';
require_once __DIR__ . '/../src/door_actions.php';

$message = null;
$type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $houseNumber = normalize_house_number((string) ($_POST['house_number'] ?? ''));

    if ($houseNumber === '') {
        $message = 'Vul een huisnummer in.';
        $type = 'error';
    } elseif (!is_valid_house_number($houseNumber)) {
        $message = house_number_validation_message();
        $type = 'error';
    } else {
        $result = trigger_ring_for_house_number($houseNumber);
        $message = (string) ($result['message'] ?? 'Onbekende status.');
        $type = !empty($result['ok']) ? 'success' : 'error';
    }
}

render_shell_start('Bel de bewoner', 'Scan de QR en vul het huisnummer in.');
echo flash_html($message, $type);
?>
<form method="post" class="form">
    <label>Huisnummer
        <input type="number" name="house_number" placeholder="Bijv. 117" required min="117" max="156" step="1" inputmode="numeric">
    </label>

    <button type="submit">Aanbellen</button>
</form>

<div class="link-row">
    <a href="/login.php">Bewoner login</a>
</div>
<?php
render_shell_end();
