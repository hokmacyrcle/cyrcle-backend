<?php

namespace App\Services\Payroll;

use App\Models\EmploymentInformation;
use App\Models\TaxDetail;

/**
 * Turns what we record about an employee into the codes the ATO asks for in Single Touch Payroll Phase 2
 * (employment basis and the 6-character tax treatment code), and says what is still missing.
 */
class StpMapper
{
    /** One-letter employment basis: F full time, P part time, C casual. Null when it cannot be worked out. */
    public static function employmentBasis(?EmploymentInformation $job, float $defaultWeeklyHours): ?string
    {
        return match ($job?->employment_basis) {
            'Full-time' => 'F',
            'Part-time' => 'P',
            'Casual' => 'C',
            // The ATO has no fixed-term code, so a fixed-term employee is reported by their working pattern.
            'Fixed-term' => $job->hours_per_week ? ((float) $job->hours_per_week >= $defaultWeeklyHours ? 'F' : 'P') : null,
            default => null,
        };
    }

    /**
     * Tax treatment code: category and option (2 letters), study loan (S or X), then 3 Medicare levy characters.
     * We do not record Medicare levy variations or exemptions yet, so those are X (not applicable).
     * Null when the code depends on something we do not record.
     */
    public static function taxTreatmentCode(?TaxDetail $tax, bool $hasTfn): ?string
    {
        $foreign = $tax && $tax->residency_status === 'Foreign resident';
        if (!$hasTfn) {
            return ($foreign ? 'NF' : 'NA') . 'XXXX';
        }
        if ($tax && $tax->tax_scale_type === 'Working holiday maker') {
            return null;   // HR, HU or HF depends on whether the business is registered as a working holiday maker employer
        }
        if ($foreign) {
            return 'FFXXXX';
        }
        $threshold = !$tax || $tax->claim_tax_free_threshold ? 'T' : 'N';
        $loan = $tax && $tax->has_study_training_loans ? 'S' : 'X';
        return 'R' . $threshold . $loan . 'XXX';
    }
}
