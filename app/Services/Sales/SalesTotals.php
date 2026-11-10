<?php

namespace App\Services\Sales;

/**
 * Invoice / quote maths for Australian GST.
 *
 * All arithmetic is done in whole cents so totals never drift by a cent.
 *
 *  amounts_are = exclusive  -> the prices typed in are before GST; GST is added on top
 *  amounts_are = inclusive  -> the prices typed in already contain GST; GST is worked out from them (rate / (100 + rate))
 *  amounts_are = no_gst     -> no GST at all (business not registered for GST)
 *
 * A discount is shared across the lines in proportion to their value, and GST is worked out on each
 * discounted line, so the GST shown is always correct for what the customer is actually charged.
 *
 * The same rules are used by the app screen (sales-totals.ts) so what you see while typing is what is saved.
 */
final class SalesTotals
{
    public const GST_RATE = 10;

    /** Largest invoice or quote we accept (before GST), in dollars. */
    public const MAX_TOTAL = 9999999.99;

    /**
     * @param  array<int, array{quantity: float|int|string, rate: float|int|string, gst_rate?: float|int|string}>  $lines
     * @return array{lines: array<int, array<string, float>>, gross: float, discount: float, subtotal: float, gst: float, total: float}
     */
    public static function compute(array $lines, string $amountsAre = 'exclusive', string $discountMethod = 'percentage', float|int|string $discountValue = 0): array
    {
        $noGst = $amountsAre === 'no_gst';
        $inclusive = $amountsAre === 'inclusive';

        // Quantity and price are held as whole hundredths / cents so nothing depends on floating point rounding.
        // (Callers keep the invoice under MAX_TOTAL so these integer products cannot overflow.)
        $entered = [];
        foreach ($lines as $line) {
            $qtyHundredths = (int) round(((float) ($line['quantity'] ?? 0)) * 100);
            $rateCents = (int) round(((float) ($line['rate'] ?? 0)) * 100);
            $entered[] = intdiv($qtyHundredths * $rateCents * 2 + 100, 200); // half-up
        }
        $sum = array_sum($entered);

        $discount = 0;
        $value = max(0.0, (float) $discountValue);
        if ($sum > 0 && $value > 0) {
            if ($discountMethod === 'fixed') {
                $discount = (int) round($value * 100);
            } else {
                $pctHundredths = (int) round(min($value, 100) * 100);
                $discount = intdiv($sum * $pctHundredths * 2 + 10000, 20000); // half-up
            }
            $discount = min($discount, $sum);
        }

        $out = [];
        $totEx = 0;
        $totGst = 0;
        $totInc = 0;
        $running = 0;
        $allocatedBefore = 0;

        foreach ($entered as $i => $amount) {
            // Share of the discount for this line (cumulative rounding makes the shares add up exactly).
            $running += $amount;
            $allocatedUpToHere = $sum > 0 ? intdiv($discount * $running * 2 + $sum, 2 * $sum) : 0; // half-up
            $share = $allocatedUpToHere - $allocatedBefore;
            $allocatedBefore = $allocatedUpToHere;

            $after = $amount - $share;
            $rate = $noGst ? 0 : (int) round((float) ($lines[$i]['gst_rate'] ?? self::GST_RATE));

            if ($noGst || $rate === 0) {
                $ex = $after;
                $gst = 0;
                $inc = $after;
            } elseif ($inclusive) {
                $inc = $after;
                $gst = intdiv($inc * $rate * 2 + (100 + $rate), 2 * (100 + $rate)); // half-up
                $ex = $inc - $gst;
            } else {
                $ex = $after;
                $gst = intdiv($ex * $rate * 2 + 100, 200); // half-up
                $inc = $ex + $gst;
            }

            $out[] = [
                'entered' => $amount / 100.0,
                'ex_gst' => $ex / 100.0,
                'gst' => $gst / 100.0,
                'inc_gst' => $inc / 100.0,
            ];
            $totEx += $ex;
            $totGst += $gst;
            $totInc += $inc;
        }

        return [
            'lines' => $out,
            'gross' => $sum / 100.0,
            'discount' => $discount / 100.0,
            'subtotal' => $totEx / 100.0,
            'gst' => $totGst / 100.0,
            'total' => $totInc / 100.0,
        ];
    }
}
