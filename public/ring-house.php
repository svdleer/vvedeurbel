<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/view.php';
require_once __DIR__ . '/../src/house_number.php';
require_once __DIR__ . '/../src/door_actions.php';

$houseNumber = normalize_house_number((string) ($_GET['house_number'] ?? $_POST['house_number'] ?? ''));
$message = null;
$type = 'info';

if ($houseNumber === '' || !is_valid_house_number($houseNumber)) {
    $message = $houseNumber === '' ? 'Geen huisnummer opgegeven.' : house_number_validation_message();
    $type = 'error';
} else {
    $result = trigger_ring_for_house_number($houseNumber);
    $message = (string) ($result['message'] ?? 'Onbekende status.');
    $type = !empty($result['ok']) ? 'success' : 'error';
}

render_shell_start('Aanbellen huisnummer ' . ($houseNumber !== '' ? $houseNumber : '?'), 'Directe bel-link verwerking.');
echo flash_html($message, $type);
?>
<div class="form">
    <p class="muted">Je kunt deze pagina opnieuw openen om opnieuw aan te bellen voor hetzelfde huisnummer.</p>
</div>

<div class="link-row">
    <a href="/index.php">Terug naar belpagina</a>
</div>
<?php
render_shell_end();
