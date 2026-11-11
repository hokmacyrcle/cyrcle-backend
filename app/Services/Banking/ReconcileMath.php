<?php

namespace App\Services\Banking;

/** Money maths for reconciling, done in whole cents so there are no rounding surprises. */
class ReconcileMath
{
    public static function cents($value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /** Statement closing balance minus (opening balance + the ticked lines). Zero means it agrees. */
    public static function difference($opening, $closing, array $amounts): int
    {
        $sum = 0;
        foreach ($amounts as $a) {
            $sum += self::cents($a);
        }
        return self::cents($closing) - (self::cents($opening) + $sum);
    }

    public static function dollars(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . '$' . number_format(abs($cents) / 100, 2);
    }
}
