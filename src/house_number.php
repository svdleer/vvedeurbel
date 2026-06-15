<?php

declare(strict_types=1);

function normalize_house_number(string $value): string
{
    return trim($value);
}

function is_valid_house_number(string $houseNumber): bool
{
    if (!preg_match('/^\d{3}$/', $houseNumber)) {
        return false;
    }

    $number = (int) $houseNumber;
    return $number >= 117 && $number <= 156;
}

function house_number_validation_message(): string
{
    return 'Huisnummer moet 3 cijfers zijn, tussen 117 en 156.';
}
