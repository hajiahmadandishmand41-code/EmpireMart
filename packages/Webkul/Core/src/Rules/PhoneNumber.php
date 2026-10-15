<?php

namespace Webkul\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * A phone number is accepted when, ignoring the separators people write it
     * with, it carries between six and fifteen digits and no letters: that
     * covers "0700 123 456", "+93 700 123 456", "+93-700-123-456" and the
     * unseparated forms of both. Six digits is the shortest national number the
     * rule allows, and fifteen is the longest a number can be under E.164.
     *
     * It stays deliberately permissive: the store serves more than one country,
     * and a rule that knows one country's numbering plan would refuse the
     * others. Anyone who needs more can override this rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('core::validation.phone-number'));

            return;
        }

        $digits = preg_replace('/[\s\-().]/u', '', trim($value));

        // Invalid UTF-8 makes preg_replace return null; reject it as input instead of passing null to preg_match.
        if (! is_string($digits) || ! preg_match('/^\+?\d{6,15}$/', $digits)) {
            $fail(__('core::validation.phone-number'));
        }
    }
}
