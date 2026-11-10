<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Deduction extends Model
{
    use HasFactory;

    protected $primaryKey = 'deduction_id';
    protected $table = 'emp_deductions'; // Explicitly set table name

    protected $fillable = [
        'employee_id',
        'deduction_type',
        'amount',
        'deduction_category',
        'calculation_type',
        'percentage_rate',
        'frequency',
        'status',
        'is_required',
        'effective_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage_rate' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_required' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Deduction category constants
    const CATEGORY_PRE_TAX = 'pre_tax';
    const CATEGORY_POST_TAX = 'post_tax';
    const CATEGORY_GARNISHMENT = 'garnishment';
    const CATEGORY_OTHER = 'other';

    // Calculation type constants
    const CALC_FIXED_AMOUNT = 'fixed_amount';
    const CALC_PERCENTAGE = 'percentage';
    const CALC_PER_PAY_PERIOD = 'per_pay_period';

    // Frequency constants
    const FREQ_PER_PAY_PERIOD = 'per_pay_period';
    const FREQ_MONTHLY = 'monthly';
    const FREQ_QUARTERLY = 'quarterly';
    const FREQ_ANNUALLY = 'annually';

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('deduction_category', $category);
    }

    public function scopeByCalculationType($query, $calculationType)
    {
        return $query->where('calculation_type', $calculationType);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }

    public function scopeEffective($query, $date = null)
    {
        $date = $date ?? now();
        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_date')
              ->orWhere('effective_date', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', $date);
        });
    }

    public function scopePreTax($query)
    {
        return $query->where('deduction_category', self::CATEGORY_PRE_TAX);
    }

    public function scopePostTax($query)
    {
        return $query->where('deduction_category', self::CATEGORY_POST_TAX);
    }

    public function scopeGarnishment($query)
    {
        return $query->where('deduction_category', self::CATEGORY_GARNISHMENT);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getFormattedPercentageRateAttribute()
    {
        if ($this->percentage_rate) {
            return number_format($this->percentage_rate, 2) . '%';
        }
        return null;
    }

    public function getCategoryLabelAttribute()
    {
        return match($this->deduction_category) {
            self::CATEGORY_PRE_TAX => 'Pre-Tax',
            self::CATEGORY_POST_TAX => 'Post-Tax',
            self::CATEGORY_GARNISHMENT => 'Garnishment',
            self::CATEGORY_OTHER => 'Other',
            default => 'Unknown'
        };
    }

    public function getCalculationTypeLabelAttribute()
    {
        return match($this->calculation_type) {
            self::CALC_FIXED_AMOUNT => 'Fixed Amount',
            self::CALC_PERCENTAGE => 'Percentage',
            self::CALC_PER_PAY_PERIOD => 'Per Pay Period',
            default => 'Unknown'
        };
    }

    public function getFrequencyLabelAttribute()
    {
        return match($this->frequency) {
            self::FREQ_PER_PAY_PERIOD => 'Per Pay Period',
            self::FREQ_MONTHLY => 'Monthly',
            self::FREQ_QUARTERLY => 'Quarterly',
            self::FREQ_ANNUALLY => 'Annually',
            default => 'Unknown'
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_SUSPENDED => 'Suspended',
            default => 'Unknown'
        };
    }

    public function getIsActiveAttribute()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getIsInactiveAttribute()
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function getIsSuspendedAttribute()
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function getIsPreTaxAttribute()
    {
        return $this->deduction_category === self::CATEGORY_PRE_TAX;
    }

    public function getIsPostTaxAttribute()
    {
        return $this->deduction_category === self::CATEGORY_POST_TAX;
    }

    public function getIsGarnishmentAttribute()
    {
        return $this->deduction_category === self::CATEGORY_GARNISHMENT;
    }

    public function getIsFixedAmountAttribute()
    {
        return $this->calculation_type === self::CALC_FIXED_AMOUNT;
    }

    public function getIsPercentageAttribute()
    {
        return $this->calculation_type === self::CALC_PERCENTAGE;
    }

    public function getIsPerPayPeriodAttribute()
    {
        return $this->calculation_type === self::CALC_PER_PAY_PERIOD;
    }

    public function getIsEffectiveAttribute()
    {
        $now = now();
        return (!$this->effective_date || $this->effective_date <= $now) &&
               (!$this->end_date || $this->end_date >= $now);
    }

    // Methods
    public function activate()
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
        return $this;
    }

    public function deactivate()
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
        return $this;
    }

    public function suspend()
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
        return $this;
    }

    public function setAsRequired()
    {
        $this->update(['is_required' => true]);
        return $this;
    }

    public function setAsOptional()
    {
        $this->update(['is_required' => false]);
        return $this;
    }

    public function updateAmount($amount)
    {
        $this->update(['amount' => $amount]);
        return $this;
    }

    public function updatePercentageRate($percentageRate)
    {
        $this->update(['percentage_rate' => $percentageRate]);
        return $this;
    }

    public function setEffectiveDate($date)
    {
        $this->update(['effective_date' => $date]);
        return $this;
    }

    public function setEndDate($date)
    {
        $this->update(['end_date' => $date]);
        return $this;
    }

    public function calculateDeductionAmount($baseAmount = null)
    {
        switch ($this->calculation_type) {
            case self::CALC_FIXED_AMOUNT:
                return $this->amount;
            
            case self::CALC_PERCENTAGE:
                if ($baseAmount && $this->percentage_rate) {
                    return round(($baseAmount * $this->percentage_rate / 100), 2);
                }
                return 0;
            
            case self::CALC_PER_PAY_PERIOD:
                return $this->amount;
            
            default:
                return 0;
        }
    }

    public function getAnnualDeductionAmount()
    {
        switch ($this->frequency) {
            case self::FREQ_PER_PAY_PERIOD:
                // Assuming 26 pay periods per year (fortnightly)
                return $this->amount * 26;
            
            case self::FREQ_MONTHLY:
                return $this->amount * 12;
            
            case self::FREQ_QUARTERLY:
                return $this->amount * 4;
            
            case self::FREQ_ANNUALLY:
                return $this->amount;
            
            default:
                return $this->amount;
        }
    }

    // Static methods
    public static function getDeductionCategories()
    {
        return [
            self::CATEGORY_PRE_TAX => 'Pre-Tax',
            self::CATEGORY_POST_TAX => 'Post-Tax',
            self::CATEGORY_GARNISHMENT => 'Garnishment',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    public static function getCalculationTypes()
    {
        return [
            self::CALC_FIXED_AMOUNT => 'Fixed Amount',
            self::CALC_PERCENTAGE => 'Percentage',
            self::CALC_PER_PAY_PERIOD => 'Per Pay Period',
        ];
    }

    public static function getFrequencies()
    {
        return [
            self::FREQ_PER_PAY_PERIOD => 'Per Pay Period',
            self::FREQ_MONTHLY => 'Monthly',
            self::FREQ_QUARTERLY => 'Quarterly',
            self::FREQ_ANNUALLY => 'Annually',
        ];
    }

    public static function getStatuses()
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_SUSPENDED => 'Suspended',
        ];
    }

    public static function getDeductionsByEmployee($employeeId)
    {
        return self::where('employee_id', $employeeId)
                   ->orderBy('deduction_type')
                   ->get();
    }

    public static function getActiveDeductionsByEmployee($employeeId)
    {
        return self::where('employee_id', $employeeId)
                   ->where('status', self::STATUS_ACTIVE)
                   ->effective()
                   ->orderBy('deduction_type')
                   ->get();
    }

    public static function getDeductionStatistics()
    {
        return [
            'total_deductions' => self::count(),
            'active_deductions' => self::active()->count(),
            'inactive_deductions' => self::inactive()->count(),
            'suspended_deductions' => self::suspended()->count(),
            'pre_tax_deductions' => self::preTax()->count(),
            'post_tax_deductions' => self::postTax()->count(),
            'garnishment_deductions' => self::garnishment()->count(),
            'required_deductions' => self::required()->count(),
            'optional_deductions' => self::optional()->count(),
            'total_annual_amount' => self::active()->sum('amount'),
        ];
    }
}
