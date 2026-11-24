<?php

namespace Tests\Unit;

use App\Models\EmploymentInformation;
use App\Models\TaxDetail;
use App\Services\Payroll\StpMapper as M;
use PHPUnit\Framework\TestCase;

class StpMapperTest extends TestCase
{
    private function tax(array $a): TaxDetail
    {
        return (new TaxDetail())->forceFill($a + ['residency_status' => 'Australian resident', 'tax_scale_type' => 'Regular', 'claim_tax_free_threshold' => true, 'has_study_training_loans' => false]);
    }

    public function test_tax_treatment_codes(): void
    {
        $this->assertSame('RTXXXX', M::taxTreatmentCode($this->tax([]), true));
        $this->assertSame('RNXXXX', M::taxTreatmentCode($this->tax(['claim_tax_free_threshold' => false]), true));
        $this->assertSame('RTSXXX', M::taxTreatmentCode($this->tax(['has_study_training_loans' => true]), true));
        $this->assertSame('FFXXXX', M::taxTreatmentCode($this->tax(['residency_status' => 'Foreign resident']), true));
        $this->assertSame('NAXXXX', M::taxTreatmentCode($this->tax([]), false));
        $this->assertSame('NFXXXX', M::taxTreatmentCode($this->tax(['residency_status' => 'Foreign resident']), false));
        $this->assertNull(M::taxTreatmentCode($this->tax(['tax_scale_type' => 'Working holiday maker']), true));
    }

    public function test_employment_basis(): void
    {
        $job = fn ($basis, $hours = null) => (new EmploymentInformation())->forceFill(['employment_basis' => $basis, 'hours_per_week' => $hours]);
        $this->assertSame('F', M::employmentBasis($job('Full-time'), 38.0));
        $this->assertSame('P', M::employmentBasis($job('Part-time'), 38.0));
        $this->assertSame('C', M::employmentBasis($job('Casual'), 38.0));
        $this->assertSame('F', M::employmentBasis($job('Fixed-term', 38), 38.0));
        $this->assertSame('P', M::employmentBasis($job('Fixed-term', 20), 38.0));
        $this->assertNull(M::employmentBasis($job('Fixed-term'), 38.0));
    }
}
