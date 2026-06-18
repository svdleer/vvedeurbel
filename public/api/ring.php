<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/house_number.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/door_actions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = request_json();
$houseNumber = normalize_house_number((string) ($input['house_number'] ?? ''));

if ($houseNumber === '') {
    json_response(['ok' => false, 'error' => 'house_number is verplicht'], 422);
}

if (!is_valid_house_number($houseNumber)) {
    json_response(['ok' => false, 'error' => house_number_validation_message()], 422);
}

$ringResult = trigger_ring_for_house_number($houseNumber, true);
$ringId = (int) ($ringResult['ring_event_id'] ?? 0);
$resident = resident_by_house_number($houseNumber);
$notificationStatus = !empty($ringResult['outside_hours']) ? 'outside_hours' : null;

json_response([
    'ok' => true,
    'ring_event_id' => $ringId,
    'registered' => (bool) $resident,
    'available' => $resident && $notificationStatus !== 'outside_hours',
    'message' => !$resident
        ? 'Huisnummer ' . $houseNumber . ' is niet aangemeld.'
        : ($notificationStatus === 'outside_hours'
            ? 'Huisnummer ' . $houseNumber . ' is momenteel niet beschikbaar. Beschikbaarheid: ' . ($resident['notification_start_hour'] ?? 8) . ':00 - ' . ($resident['notification_end_hour'] ?? 22) . ':00'
            : 'Melding verstuurd naar bewoner van huisnummer ' . $houseNumber . '.'),
]);
