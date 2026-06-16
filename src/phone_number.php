<?php

declare(strict_types=1);

function normalize_phone_number(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[\s\-().]/', '', $value) ?? $value;

    if ($value === '') {
        return '';
    }

    if (str_starts_with($value, '00')) {
        $value = '+' . substr($value, 2);
    }

    if (str_starts_with($value, '31') && !str_starts_with($value, '+')) {
        $value = '+' . $value;
    }

    if (str_starts_with($value, '+31')) {
        $rest = substr($value, 3);
        if (str_starts_with($rest, '0')) {
            $rest = substr($rest, 1);
        }
        $value = '+31' . $rest;
    }

    if (preg_match('/^0\d{9}$/', $value)) {
        $value = '+31' . substr($value, 1);
    }

    return $value;
}

function is_valid_phone_number(string $phoneNumber): bool
{
    // Dutch mobile numbers only: +316XXXXXXXX after normalization.
    return (bool) preg_match('/^\+316\d{8}$/', $phoneNumber);
}

function phone_number_validation_message(): string
{
    return 'Telefoonnummer moet een Nederlands mobiel nummer zijn: 06..., +316..., 00316... of +31 06..., we slaan het op als +316...';
}
