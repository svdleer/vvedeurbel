<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifier.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/auth.php';

function trigger_ring_for_house_number(string $houseNumber, bool $respectAvailability = false): array
{
    $pdo = db();

    $residentStmt = $pdo->prepare('SELECT * FROM residents WHERE house_number = :house_number LIMIT 1');
    $residentStmt->execute(['house_number' => $houseNumber]);
    $resident = $residentStmt->fetch();

    $ringStmt = $pdo->prepare('INSERT INTO ring_events (house_number, resident_id, status) VALUES (:house_number, :resident_id, :status)');
    $ringStmt->execute([
        'house_number' => $houseNumber,
        'resident_id' => $resident['id'] ?? null,
        'status' => $resident ? 'pending' : 'unmatched',
    ]);

    $ringId = (int) $pdo->lastInsertId();

    if (!$resident) {
        return [
            'ok' => false,
            'registered' => false,
            'ring_event_id' => $ringId,
            'message' => 'Huisnummer ' . $houseNumber . ' is niet aangemeld.',
        ];
    }

    if ($respectAvailability && !is_resident_available($resident)) {
        $updateStmt = $pdo->prepare('UPDATE ring_events SET status = :status WHERE id = :id');
        $updateStmt->execute([
            'status' => 'outside_hours',
            'id' => $ringId,
        ]);

        return [
            'ok' => false,
            'registered' => true,
            'ring_event_id' => $ringId,
            'outside_hours' => true,
            'message' => 'Huisnummer ' . $houseNumber . ' is momenteel niet beschikbaar.',
        ];
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+2 minutes'))->format('Y-m-d H:i:s');

    $openLinkStmt = $pdo->prepare('INSERT INTO open_links (ring_event_id, token, expires_at) VALUES (:ring_event_id, :token, :expires_at)');
    $openLinkStmt->execute([
        'ring_event_id' => $ringId,
        'token' => $token,
        'expires_at' => $expiresAt,
    ]);

    $openUrl = base_url() . '/open.php?token=' . urlencode($token);
    $notifyMessage = 'Er staat iemand bij de deur voor huisnummer ' . $houseNumber . '.';
    $notifyResult = notify_resident($resident, $notifyMessage, $openUrl);

    $updateStmt = $pdo->prepare('UPDATE ring_events SET status = :status, notify_error = :notify_error WHERE id = :id');
    $updateStmt->execute([
        'status' => $notifyResult['ok'] ? 'notified' : 'notify_failed',
        'notify_error' => $notifyResult['ok'] ? null : (($notifyResult['error'] ?? null) ?: json_encode($notifyResult)),
        'id' => $ringId,
    ]);

    return [
        'ok' => (bool) $notifyResult['ok'],
        'registered' => true,
        'ring_event_id' => $ringId,
        'message' => $notifyResult['ok']
            ? 'Melding verstuurd naar bewoner van huisnummer ' . $houseNumber . '.'
            : 'Melding naar huisnummer ' . $houseNumber . ' is mislukt.',
    ];
}

function queue_direct_open_for_resident(array $resident): array
{
    $pdo = db();
    $cfg = app_config();

    $ringStmt = $pdo->prepare(
        'INSERT INTO ring_events (house_number, resident_id, status, opened_at)
         VALUES (:house_number, :resident_id, :status, NOW())'
    );
    $ringStmt->execute([
        'house_number' => $resident['house_number'],
        'resident_id' => $resident['id'],
        'status' => 'opened',
    ]);

    $ringEventId = (int) $pdo->lastInsertId();

    $cmdStmt = $pdo->prepare(
        'INSERT INTO open_commands (ring_event_id, command_token, pulse_ms, status)
         VALUES (:ring_event_id, :command_token, :pulse_ms, :status)'
    );
    $cmdStmt->execute([
        'ring_event_id' => $ringEventId,
        'command_token' => bin2hex(random_bytes(32)),
        'pulse_ms' => $cfg['door_open_pulse_ms'],
        'status' => 'queued',
    ]);

    return [
        'ok' => true,
        'ring_event_id' => $ringEventId,
        'command_id' => (int) $pdo->lastInsertId(),
        'message' => 'Deur-open commando in wachtrij gezet.',
    ];
}
