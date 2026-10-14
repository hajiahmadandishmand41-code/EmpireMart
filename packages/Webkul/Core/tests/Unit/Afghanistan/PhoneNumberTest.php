<?php

use Webkul\Core\Rules\PhoneNumber;

/**
 * Afghan customers write their number with spaces, in national or international
 * form, and the rule has to accept what they type. It lives beside the rest of
 * the Afghan store because it was relaxed for them, and because the same rule
 * runs when a phone number is saved from the storefront and from the panel.
 */
function phoneNumberFails(string $number): bool
{
    $failed = false;

    (new PhoneNumber)->validate('phone', $number, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

it('accepts the phone numbers the store is given', function (string $number) {
    expect(phoneNumberFails($number))->toBeFalse();
})->with([
    '+93700123456',
    '+93 700 123 456',
    '0700 123 456',
    '+93-700-123-456',
    '0700123456',
    '(070) 012 3456',
    '+15551234567',
]);

it('refuses what is not a phone number', function (string $number) {
    expect(phoneNumberFails($number))->toBeTrue();
})->with([
    '12345',
    'call the shop',
    '+93 700 123 4567 8901',
    '',
]);
