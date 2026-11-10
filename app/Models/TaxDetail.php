<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TaxDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'tax_detail_id';
    protected $table = 'emp_tax_details'; // Explicitly set table name

    protected $fillable = [
        'employee_id',
        'tax_file_number',
        'tfn_exemption',
        'residency_status',
        'has_approved_withholding_variation',
        'tax_scale_type',
        'has_study_training_loans',
        'claim_tax_free_threshold',
        'other_tax_offset_claimed',
        'tax_offset_amount',
        'increase_tax_withheld',
        'additional_tax_withheld',
        'eligible_for_leave_loading',
        'tax_notes',
        'is_active',
    ];

    protected $casts = [
        'has_approved_withholding_variation' => 'boolean',
        'has_study_training_loans' => 'boolean',
        'claim_tax_free_threshold' => 'boolean',
        'other_tax_offset_claimed' => 'boolean',
        'increase_tax_withheld' => 'boolean',
        'eligible_for_leave_loading' => 'boolean',
        'tax_offset_amount' => 'decimal:2',
        'additional_tax_withheld' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // TFN Exemption constants
    const TFN_EXEMPTION_WORKING_HOLIDAY = 'Working holiday maker';
    const TFN_EXEMPTION_STUDENT = 'Student';
    const TFN_EXEMPTION_PENSIONER = 'Pensioner';
    const TFN_EXEMPTION_OTHER = 'Other';

    // Residency status constants
    const RESIDENCY_AUSTRALIAN = 'Australian resident';
    const RESIDENCY_FOREIGN = 'Foreign resident';

    // Tax scale type constants
    const SCALE_REGULAR = 'Regular';
    const SCALE_NO_TFN = 'No TFN';
    const SCALE_WORKING_HOLIDAY = 'Working holiday maker';
    const SCALE_STUDENT = 'Student';
    const SCALE_PENSIONER = 'Pensioner';

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAustralianResidents($query)
    {
        return $query->where('residency_status', self::RESIDENCY_AUSTRALIAN);
    }

    public function scopeForeignResidents($query)
    {
        return $query->where('residency_status', self::RESIDENCY_FOREIGN);
    }

    public function scopeByTaxScale($query, $scaleType)
    {
        return $query->where('tax_scale_type', $scaleType);
    }

    public function scopeWithTFN($query)
    {
        return $query->whereNotNull('tax_file_number');
    }

    public function scopeWithoutTFN($query)
    {
        return $query->whereNull('tax_file_number');
    }

    public function scopeWithStudyLoans($query)
    {
        return $query->where('has_study_training_loans', true);
    }

    public function scopeWithTaxOffset($query)
    {
        return $query->where('other_tax_offset_claimed', true);
    }

    public function scopeWithAdditionalWithholding($query)
    {
        return $query->where('increase_tax_withheld', true);
    }

    public function scopeEligibleForLeaveLoading($query)
    {
        return $query->where('eligible_for_leave_loading', true);
    }

    // Accessors
    public function getIsAustralianResidentAttribute()
    {
        return $this->residency_status === self::RESIDENCY_AUSTRALIAN;
    }

    public function getIsForeignResidentAttribute()
    {
        return $this->residency_status === self::RESIDENCY_FOREIGN;
    }

    public function getHasTFNAttribute()
    {
        return !empty($this->tax_file_number);
    }

    public function getFormattedTFNAttribute()
    {
        if (empty($this->tax_file_number)) {
            return null;
        }
        
        // Format TFN as XXX-XXX-XXX
        $tfn = $this->tax_file_number;
        if (strlen($tfn) === 9) {
            return substr($tfn, 0, 3) . '-' . substr($tfn, 3, 3) . '-' . substr($tfn, 6, 3);
        }
        
        return $tfn;
    }

    public function getTaxOffsetPerPayPeriodAttribute()
    {
        if (!$this->other_tax_offset_claimed || $this->tax_offset_amount <= 0) {
            return 0.00;
        }
        
        // This would typically be calculated based on pay frequency
        // For now, assuming fortnightly (26 pay periods per year)
        return round($this->tax_offset_amount / 26, 2);
    }

    public function getTotalTaxWithheldPerPayPeriodAttribute()
    {
        $baseWithholding = 0; // This would come from tax scale calculation
        
        if ($this->increase_tax_withheld) {
            $baseWithholding += $this->additional_tax_withheld;
        }
        
        return $baseWithholding;
    }

    public function getTaxStatusSummaryAttribute()
    {
        $status = [];
        
        if ($this->has_tfn) {
            $status[] = 'Has TFN';
        } else {
            $status[] = 'No TFN';
        }
        
        if ($this->tfn_exemption) {
            $status[] = 'TFN Exemption: ' . $this->tfn_exemption;
        }
        
        $status[] = $this->residency_status;
        
        if ($this->has_approved_withholding_variation) {
            $status[] = 'Withholding Variation Approved';
        }
        
        if ($this->has_study_training_loans) {
            $status[] = 'Study/Training Loans';
        }
        
        if ($this->claim_tax_free_threshold) {
            $status[] = 'Claims Tax-Free Threshold';
        }
        
        if ($this->other_tax_offset_claimed) {
            $status[] = 'Tax Offset: $' . number_format($this->tax_offset_amount, 2);
        }
        
        if ($this->increase_tax_withheld) {
            $status[] = 'Additional Withholding: $' . number_format($this->additional_tax_withheld, 2);
        }
        
        if ($this->eligible_for_leave_loading) {
            $status[] = 'Eligible for Leave Loading';
        }
        
        return implode(', ', $status);
    }

    // Methods
    public function setTFN($tfn)
    {
        // Remove any non-numeric characters
        $tfn = preg_replace('/[^0-9]/', '', $tfn);
        
        if (strlen($tfn) === 9) {
            $this->update([
                'tax_file_number' => $tfn,
                'tfn_exemption' => null, // Clear exemption when TFN is set
            ]);
            return true;
        }
        
        return false;
    }

    public function clearTFN()
    {
        $this->update([
            'tax_file_number' => null,
            'tfn_exemption' => null,
        ]);
    }

    public function setTFNExemption($exemption)
    {
        if (in_array($exemption, [
            self::TFN_EXEMPTION_WORKING_HOLIDAY,
            self::TFN_EXEMPTION_STUDENT,
            self::TFN_EXEMPTION_PENSIONER,
            self::TFN_EXEMPTION_OTHER
        ])) {
            $this->update([
                'tfn_exemption' => $exemption,
                'tax_file_number' => null, // Clear TFN when exemption is set
            ]);
            return true;
        }
        
        return false;
    }

    public function setResidencyStatus($status)
    {
        if (in_array($status, [self::RESIDENCY_AUSTRALIAN, self::RESIDENCY_FOREIGN])) {
            $this->update(['residency_status' => $status]);
            return true;
        }
        
        return false;
    }

    public function setTaxScale($scaleType)
    {
        if (in_array($scaleType, [
            self::SCALE_REGULAR,
            self::SCALE_NO_TFN,
            self::SCALE_WORKING_HOLIDAY,
            self::SCALE_STUDENT,
            self::SCALE_PENSIONER
        ])) {
            $this->update(['tax_scale_type' => $scaleType]);
            return true;
        }
        
        return false;
    }

    public function setTaxOffset($amount)
    {
        if ($amount >= 0) {
            $this->update([
                'other_tax_offset_claimed' => $amount > 0,
                'tax_offset_amount' => $amount,
            ]);
            return true;
        }
        
        return false;
    }

    public function setAdditionalWithholding($amount)
    {
        if ($amount >= 0) {
            $this->update([
                'increase_tax_withheld' => $amount > 0,
                'additional_tax_withheld' => $amount,
            ]);
            return true;
        }
        
        return false;
    }

    public function toggleStudyLoans($hasLoans = null)
    {
        if ($hasLoans === null) {
            $hasLoans = !$this->has_study_training_loans;
        }
        
        $this->update(['has_study_training_loans' => $hasLoans]);
        return $this->has_study_training_loans;
    }

    public function toggleTaxFreeThreshold($claim = null)
    {
        if ($claim === null) {
            $claim = !$this->claim_tax_free_threshold;
        }
        
        $this->update(['claim_tax_free_threshold' => $claim]);
        return $this->claim_tax_free_threshold;
    }

    public function toggleLeaveLoading($eligible = null)
    {
        if ($eligible === null) {
            $eligible = !$this->eligible_for_leave_loading;
        }
        
        $this->update(['eligible_for_leave_loading' => $eligible]);
        return $this->eligible_for_leave_loading;
    }

    public function approveWithholdingVariation()
    {
        $this->update(['has_approved_withholding_variation' => true]);
    }

    public function revokeWithholdingVariation()
    {
        $this->update(['has_approved_withholding_variation' => false]);
    }

    // Static methods for common queries
    public static function getEmployeesByResidency($residency)
    {
        return self::where('residency_status', $residency)
                   ->with('employee')
                   ->get();
    }

    public static function getEmployeesByTaxScale($scaleType)
    {
        return self::where('tax_scale_type', $scaleType)
                   ->with('employee')
                   ->get();
    }

    public static function getEmployeesWithStudyLoans()
    {
        return self::withStudyLoans()->with('employee')->get();
    }

    public static function getEmployeesWithTaxOffset()
    {
        return self::withTaxOffset()->with('employee')->get();
    }

    public static function getEmployeesWithAdditionalWithholding()
    {
        return self::withAdditionalWithholding()->with('employee')->get();
    }

    public static function getTaxStatistics()
    {
        return [
            'total_tax_records' => self::count(),
            'active_tax_records' => self::active()->count(),
            'australian_residents' => self::australianResidents()->count(),
            'foreign_residents' => self::foreignResidents()->count(),
            'with_tfn' => self::withTFN()->count(),
            'without_tfn' => self::withoutTFN()->count(),
            'with_study_loans' => self::withStudyLoans()->count(),
            'with_tax_offset' => self::withTaxOffset()->count(),
            'with_additional_withholding' => self::withAdditionalWithholding()->count(),
            'eligible_for_leave_loading' => self::eligibleForLeaveLoading()->count(),
            'withholding_variation_approved' => self::where('has_approved_withholding_variation', true)->count(),
        ];
    }

    public static function validateTFN($tfn)
    {
        // Remove non-numeric characters
        $tfn = preg_replace('/[^0-9]/', '', $tfn);
        
        // Check length
        if (strlen($tfn) !== 9) {
            return false;
        }
        
        // Basic TFN validation algorithm (simplified)
        $weights = [1, 4, 3, 7, 5, 8, 6, 9, 10];
        $sum = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($tfn[$i]) * $weights[$i];
        }
        
        return $sum % 11 === 0;
    }
}
