<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Checks the 11-digit ABN checksum (ATO algorithm). Does not look the ABN up on the ABR. */
class ValidAbn implements ValidationRule
{
    public static function check(?string $value): bool
    {
        $digits = preg_replace('/\s+/', '', (string) $value);
        if (!preg_match('/^\d{11}$/', $digits)) {
            return false;
        }
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $d = array_map('intval', str_split($digits));
        $d[0] -= 1;
        $sum = 0;
        foreach ($d as $i => $n) {
            $sum += $n * $weights[$i];
        }
        return $sum % 89 === 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!self::check($value)) {
            $fail('Enter a valid 11-digit ABN.');
        }
    }
}
