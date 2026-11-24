<?php

namespace Tests\Unit;

use App\Services\Payroll\PayCalculator as P;
use PHPUnit\Framework\TestCase;

class PayCalculatorTest extends TestCase
{
    /** Weekly amounts from the published 2026-27 tax table (ATO NAT 1005): earnings => [scale 2, scale 1]. */
    public function test_weekly_amounts_match_the_published_table(): void
    {
        $table = [400 => [6, 72], 600 => [42, 120], 800 => [83, 184], 1000 => [138, 249],
                  1500 => [299, 409], 2000 => [459, 569], 3000 => [807, 942], 4000 => [1225, 1387]];
        foreach ($table as $earnings => [$s2, $s1]) {
            $this->assertSame($s2 * 100, P::withholding($earnings * 100, P::WEEKLY, P::SCALE_THRESHOLD), "scale 2 at $earnings");
            $this->assertSame($s1 * 100, P::withholding($earnings * 100, P::WEEKLY, P::SCALE_NO_THRESHOLD), "scale 1 at $earnings");
        }
    }

    public function test_nothing_withheld_below_the_threshold(): void
    {
        $this->assertSame(0, P::withholding(36199, P::WEEKLY, P::SCALE_THRESHOLD));
        $this->assertSame(0, P::withholding(0, P::WEEKLY, P::SCALE_THRESHOLD));
    }

    public function test_fortnightly_and_monthly_conversion(): void
    {
        $this->assertSame(27600, P::withholding(200000, P::FORTNIGHTLY, P::SCALE_THRESHOLD));
        $this->assertSame(59800, P::withholding(433333, P::MONTHLY, P::SCALE_THRESHOLD));
    }

    public function test_no_tfn_withholds_47_percent_for_residents_and_45_for_foreign_residents(): void
    {
        $this->assertSame(47000, P::withholding(100050, P::WEEKLY, P::SCALE_NO_TFN));
        $this->assertSame(45000, P::withholding(100050, P::WEEKLY, P::SCALE_NO_TFN, true));
    }

    public function test_super_and_gross_rounding(): void
    {
        $this->assertSame(24000, P::superCents(200000));
        $this->assertSame(14815, P::superCents(123457));
        $this->assertSame(326923, P::salaryForPeriodCents(8500000, P::FORTNIGHTLY));
        $this->assertSame(247000, P::hourlyGrossCents(7600, 3250));
        $this->assertSame(11099, P::hourlyGrossCents(333, 3333));
    }

    /** Study and training loan component, ATO Schedule 8 (payments from 1 July 2026). */
    public function test_study_loan_repayments(): void
    {
        $this->assertSame(0, P::studyLoan(133799, P::WEEKLY, P::SCALE_THRESHOLD));        // below the $1,337 weekly threshold
        $this->assertSame(10000, P::studyLoan(200000, P::WEEKLY, P::SCALE_THRESHOLD));    // 0.15 x 2000.99 - 200.5615 = 99.59
        $this->assertSame(20000, P::studyLoan(400000, P::FORTNIGHTLY, P::SCALE_THRESHOLD));
        $this->assertSame(0, P::studyLoan(98700, P::WEEKLY, P::SCALE_NO_THRESHOLD));
        $this->assertSame(0, P::studyLoan(500000, P::WEEKLY, P::SCALE_NO_TFN));           // nothing without a tax file number
    }
}
