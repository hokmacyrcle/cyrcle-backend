<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Checks the 9-digit Tax File Number checksum (ATO algorithm). Does not check that the number is issued to the person. */
class ValidTfn implements ValidationRule
{
    public static function check(?string $value): bool
    {
        $digits = preg_replace('/\s+/', '', (string) $value);
        if (!preg_match('/^\d{9}$/', $digits)) {
            return false;
        }
        $weights = [1, 4, 3, 7, 5, 8, 6, 9, 10];
        $sum = 0;
        foreach (str_split($digits) as $i => $n) {
            $sum += ((int) $n) * $weights[$i];
        }
        return $sum % 11 === 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!self::check($value)) {
            $fail('Enter a valid 9-digit tax file number.');
        }
    }
}
