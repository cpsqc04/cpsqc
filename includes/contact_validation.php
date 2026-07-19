<?php

/**
 * Shared contact-number helpers for CPSQC APIs.
 * Contact numbers must be exactly 11 digits (numbers only).
 */

const CPSQC_CONTACT_LENGTH = 11;

function normalizeContactDigits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function isValidContactNumber(?string $value): bool
{
    return (bool) preg_match('/^\d{' . CPSQC_CONTACT_LENGTH . '}$/', trim((string) $value));
}

function isValidContactNumberOptional(?string $value): bool
{
    $trimmed = trim((string) $value);
    return $trimmed === '' || isValidContactNumber($trimmed);
}

/**
 * @return string|null Error message, or null when valid.
 */
function validateContactNumber(?string $value, string $label = 'Contact number'): ?string
{
    if (!isValidContactNumber($value)) {
        return $label . ' must be exactly ' . CPSQC_CONTACT_LENGTH . ' digits (numbers only).';
    }

    return null;
}

/**
 * @return string|null Error message, or null when valid / empty.
 */
function validateContactNumberOptional(?string $value, string $label = 'Contact number'): ?string
{
    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return null;
    }

    if (!isValidContactNumber($trimmed)) {
        return $label . ' must be exactly ' . CPSQC_CONTACT_LENGTH . ' digits (numbers only) when provided.';
    }

    return null;
}
