<?php

namespace App\Services\Payroll;

/**
 * PAYG withholding and super for a single payment, in whole cents (no floating point).
 *
 * Source: ATO "Schedule 1 - Statement of formulas for calculating amounts to be withheld",
 * Taxation Administration (Withholding Schedules) Instrument 2026 (LI 2026/18), commencing 1 July 2026.
 * Scales 1 and 2 already include the Medicare levy. Coefficients are stored times 10,000 (0.1500 => 1500).
 *
 * Not covered, and flagged to the person instead of guessed: study and training loan repayments, foreign
 * residents with a TFN, working holiday makers, Medicare levy variations, and allowances or bonuses.
 */
class PayCalculator
{
    public const WEEKLY = 'Weekly';
    public const FORTNIGHTLY = 'Fortnightly';
    public const MONTHLY = 'Monthly';

    public const SCALE_NO_THRESHOLD = 'scale1';   // tax-free threshold not claimed
    public const SCALE_THRESHOLD = 'scale2';      // tax-free threshold claimed
    public const SCALE_NO_TFN = 'no_tfn';

    /** Super guarantee rate, in hundredths of a percent: 12.00% */
    public const SUPER_RATE_HUNDREDTHS = 1200;

    /**
     * [weekly earnings upper limit in whole dollars (x must be below it), a * 10000, b * 10000].
     * A null coefficient pair means nothing is withheld.
     */
    private const SCALE_2 = [
        [362, null, null],
        [538, 1500, 543462],
        [673, 2500, 1082135],
        [721, 1700, 543473],
        [865, 1790, 608377],
        [1282, 3227, 1851935],
        [2596, 3200, 1817319],
        [3653, 3900, 3634627],
        [PHP_INT_MAX, 4700, 6557704],
    ];

    private const SCALE_1 = [
        [188, 1500, 1500],
        [371, 2084, 110185],
        [515, 1790, 1066],
        [932, 3227, 741674],
        [2246, 3200, 716508],
        [3303, 3900, 2288816],
        [PHP_INT_MAX, 4700, 4931893],
    ];

    /**
     * Study and training loan (HELP, VSL, SSL, ABSTUDY SSL, TSL) component, ATO Schedule 8 for payments from 1 July 2026.
     * Same layout as the tax tables: [weekly earnings upper limit in dollars, a * 10000, b * 10000].
     * Used where the tax-free threshold is claimed, or the payee is a foreign resident.
     */
    private const STSL_THRESHOLD = [
        [1337, null, null],
        [2494, 1500, 2005615],
        [3577, 1700, 2504527],
        [PHP_INT_MAX, 1000, 0],
    ];

    /** Study loan component where the tax-free threshold is not claimed. */
    private const STSL_NO_THRESHOLD = [
        [987, null, null],
        [2144, 1500, 1480615],
        [2727, 1700, 1909527],
        [PHP_INT_MAX, 1000, 0],
    ];

    /**
     * Study and training loan repayment to withhold with this payment, in cents. $scale is the tax scale used for the
     * payment. Nothing is withheld for a payee without a tax file number.
     */
    public static function studyLoan(int $grossCents, string $frequency, string $scale): int
    {
        if ($grossCents <= 0 || $scale === self::SCALE_NO_TFN) {
            return 0;
        }
        $table = $scale === self::SCALE_NO_THRESHOLD ? self::STSL_NO_THRESHOLD : self::STSL_THRESHOLD;
        $weeklyDollars = self::weeklyWithholdingDollars(self::weeklyEarningsCents($grossCents, $frequency), $table);

        return match ($frequency) {
            self::WEEKLY => $weeklyDollars * 100,
            self::FORTNIGHTLY => $weeklyDollars * 2 * 100,
            self::MONTHLY => intdiv($weeklyDollars * 26 + 3, 6) * 100,   // weekly x 13 / 3, nearest dollar
            default => throw new \InvalidArgumentException('Unknown pay frequency.'),
        };
    }

    /** Withholding for one payment. $grossCents is the taxable earnings for the period. */
    public static function withholding(int $grossCents, string $frequency, string $scale, bool $foreignResident = false): int
    {
        if ($grossCents <= 0) {
            return 0;
        }
        if ($scale === self::SCALE_NO_TFN) {
            // Cents are ignored in both the earnings and the result. 47% resident, 45% foreign resident.
            return intdiv(intdiv($grossCents, 100) * ($foreignResident ? 45 : 47), 100) * 100;
        }
        $table = $scale === self::SCALE_THRESHOLD ? self::SCALE_2 : self::SCALE_1;
        $weeklyDollars = self::weeklyWithholdingDollars(self::weeklyEarningsCents($grossCents, $frequency), $table);

        return match ($frequency) {
            self::WEEKLY => $weeklyDollars * 100,
            self::FORTNIGHTLY => $weeklyDollars * 2 * 100,
            // weekly amount x 13 / 3, rounded to the nearest dollar (half up)
            self::MONTHLY => intdiv($weeklyDollars * 26 + 3, 6) * 100,
            default => throw new \InvalidArgumentException('Unknown pay frequency.'),
        };
    }

    /** Weekly earnings "x" in cents: whole dollars plus 99 cents, as the Schedule requires. */
    public static function weeklyEarningsCents(int $grossCents, string $frequency): int
    {
        switch ($frequency) {
            case self::WEEKLY:
                $dollars = intdiv($grossCents, 100);
                break;
            case self::FORTNIGHTLY:
                $dollars = intdiv($grossCents, 200);
                break;
            case self::MONTHLY:
                if ($grossCents % 100 === 33) {
                    $grossCents += 1; // the Schedule: if the monthly amount ends in 33 cents, add one cent
                }
                $dollars = intdiv($grossCents * 3, 1300);
                break;
            default:
                throw new \InvalidArgumentException('Unknown pay frequency.');
        }
        return $dollars * 100 + 99;
    }

    /** y = ax - b for the weekly amount, rounded to the nearest dollar (half up). Never negative. */
    private static function weeklyWithholdingDollars(int $xCents, array $table): int
    {
        foreach ($table as [$limit, $a, $b]) {
            if ($xCents < $limit * 100) {
                if ($a === null) {
                    return 0;
                }
                $num = $a * $xCents - $b * 100;           // y in millionths of a dollar
                return $num <= 0 ? 0 : intdiv($num + 500000, 1000000);
            }
        }
        return 0;
    }

    /** Super on ordinary earnings, to the cent (half up). The rate is in hundredths of a percent (1200 = 12.00%). */
    public static function superCents(int $earningsCents, int $rateHundredths = self::SUPER_RATE_HUNDREDTHS): int
    {
        if ($earningsCents <= 0) {
            return 0;
        }
        return intdiv($earningsCents * $rateHundredths * 2 + 10000, 20000);
    }

    /** Gross for one pay period from a yearly salary, to the cent (half up). */
    public static function salaryForPeriodCents(int $annualCents, string $frequency): int
    {
        $periods = match ($frequency) {
            self::WEEKLY => 52, self::FORTNIGHTLY => 26, self::MONTHLY => 12,
            default => throw new \InvalidArgumentException('Unknown pay frequency.'),
        };
        return intdiv($annualCents * 2 + $periods, $periods * 2);
    }

    /** Gross for hours worked: hours in hundredths x hourly rate in cents, to the cent (half up). */
    public static function hourlyGrossCents(int $hoursHundredths, int $rateCents): int
    {
        return intdiv($hoursHundredths * $rateCents * 2 + 100, 200);
    }
}
