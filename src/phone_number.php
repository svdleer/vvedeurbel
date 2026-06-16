<?php

declare(strict_types=1);

function normalize_phone_number(string $value): string
{
    return trim($value);
}

function is_valid_phone_number(string $phoneNumber): bool
{
    // E.164-ish: + followed by 8 to 15 digits.
    return (bool) preg_match('/^\+\d{8,15}$/', $phoneNumber);
}

function phone_number_validation_message(): string
{
    return 'Telefoonnummer moet in E.164 formaat zijn, bijvoorbeeld +31612345678.';
}
