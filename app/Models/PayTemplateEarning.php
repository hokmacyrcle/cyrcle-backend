<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PayTemplateEarning extends Model
{
    use HasFactory;

    protected $primaryKey = 'earnings_id';

    protected $fillable = [
        'pay_template_id',
        'earnings_rate_name',
        'calculation_type',
        'estimate_rate',
        'hours',
        'rate',
        'total',
        'fixed_amount',
        'mixed_fixed_amount',
        'mixed_rate',
        'mixed_total',
        'display_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'estimate_rate' => 'decimal:6',
        'hours' => 'decimal:4',
        'rate' => 'decimal:6',
        'total' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'mixed_fixed_amount' => 'decimal:2',
        'mixed_rate' => 'decimal:6',
        'mixed_total' => 'decimal:2',
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Calculation type constants
    const CALC_HOURS_RATE = 'hours_rate';
    const CALC_FIXED_AMOUNT = 'fixed_amount';
    const CALC_MIXED = 'mixed';

    // Relationships
    public function payTemplate()
    {
        return $this->belongsTo(PayTemplate::class, 'pay_template_id', 'pay_template_id');
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
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByCalculationType($query, $type)
    {
        return $query->where('calculation_type', $type);
    }

    public function scopeHoursRate($query)
    {
        return $query->where('calculation_type', self::CALC_HOURS_RATE);
    }

    public function scopeFixedAmount($query)
    {
        return $query->where('calculation_type', self::CALC_FIXED_AMOUNT);
    }

    public function scopeMixed($query)
    {
        return $query->where('calculation_type', self::CALC_MIXED);
    }

    public function scopeByDisplayOrder($query)
    {
        return $query->orderBy('display_order', 'asc');
    }

    // Accessors
    public function getFormattedHoursAttribute()
    {
        return $this->hours ? number_format($this->hours, 4) : null;
    }

    public function getFormattedRateAttribute()
    {
        return $this->rate ? number_format($this->rate, 6) : null;
    }

    public function getFormattedTotalAttribute()
    {
        return $this->total ? '$' . number_format($this->total, 2) : null;
    }

    public function getFormattedFixedAmountAttribute()
    {
        return $this->fixed_amount ? '$' . number_format($this->fixed_amount, 2) : null;
    }

    public function getFormattedEstimateRateAttribute()
    {
        return $this->estimate_rate ? number_format($this->estimate_rate, 6) : null;
    }

    public function getCalculationTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->calculation_type));
    }

    public function getIsHoursRateAttribute()
    {
        return $this->calculation_type === self::CALC_HOURS_RATE;
    }

    public function getIsFixedAmountAttribute()
    {
        return $this->calculation_type === self::CALC_FIXED_AMOUNT;
    }

    public function getIsMixedAttribute()
    {
        return $this->calculation_type === self::CALC_MIXED;
    }

    // Methods
    public function calculateTotal()
    {
        switch ($this->calculation_type) {
            case self::CALC_HOURS_RATE:
                if ($this->hours && $this->rate) {
                    $this->total = $this->hours * $this->rate;
                }
                break;
                
            case self::CALC_FIXED_AMOUNT:
                $this->total = $this->fixed_amount;
                break;
                
            case self::CALC_MIXED:
                if ($this->mixed_fixed_amount && $this->mixed_rate) {
                    $this->mixed_total = $this->mixed_fixed_amount * $this->mixed_rate;
                }
                $this->total = $this->mixed_fixed_amount + ($this->mixed_total ?? 0);
                break;
        }
        
        $this->save();
        return $this;
    }

    public function setCalculationType($type)
    {
        $this->calculation_type = $type;
        
        // Clear irrelevant fields based on calculation type
        switch ($type) {
            case self::CALC_HOURS_RATE:
                $this->fixed_amount = null;
                $this->mixed_fixed_amount = null;
                $this->mixed_rate = null;
                $this->mixed_total = null;
                break;
                
            case self::CALC_FIXED_AMOUNT:
                $this->hours = null;
                $this->rate = null;
                $this->mixed_fixed_amount = null;
                $this->mixed_rate = null;
                $this->mixed_total = null;
                break;
                
            case self::CALC_MIXED:
                $this->hours = null;
                $this->rate = null;
                $this->fixed_amount = null;
                break;
        }
        
        $this->save();
        return $this;
    }

    public function activate()
    {
        $this->update(['is_active' => true]);
        return $this;
    }

    public function deactivate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    public function moveUp()
    {
        $previousEarning = $this->payTemplate->earnings()
            ->where('display_order', '<', $this->display_order)
            ->orderBy('display_order', 'desc')
            ->first();
            
        if ($previousEarning) {
            $tempOrder = $this->display_order;
            $this->update(['display_order' => $previousEarning->display_order]);
            $previousEarning->update(['display_order' => $tempOrder]);
        }
        
        return $this;
    }

    public function moveDown()
    {
        $nextEarning = $this->payTemplate->earnings()
            ->where('display_order', '>', $this->display_order)
            ->orderBy('display_order', 'asc')
            ->first();
            
        if ($nextEarning) {
            $tempOrder = $this->display_order;
            $this->update(['display_order' => $nextEarning->display_order]);
            $nextEarning->update(['display_order' => $tempOrder]);
        }
        
        return $this;
    }

    public function updateHoursAndRate($hours, $rate)
    {
        $this->update([
            'hours' => $hours,
            'rate' => $rate,
        ]);
        
        $this->calculateTotal();
        return $this;
    }

    public function updateFixedAmount($amount)
    {
        $this->update(['fixed_amount' => $amount]);
        $this->calculateTotal();
        return $this;
    }

    public function updateMixedValues($fixedAmount, $rate)
    {
        $this->update([
            'mixed_fixed_amount' => $fixedAmount,
            'mixed_rate' => $rate,
        ]);
        
        $this->calculateTotal();
        return $this;
    }

    // Static methods
    public static function getCalculationTypes()
    {
        return [
            self::CALC_HOURS_RATE => 'Hours & Rate',
            self::CALC_FIXED_AMOUNT => 'Fixed Amount',
            self::CALC_MIXED => 'Mixed (Fixed + Rate)',
        ];
    }

    public static function validateCalculationType($type)
    {
        return in_array($type, [
            self::CALC_HOURS_RATE,
            self::CALC_FIXED_AMOUNT,
            self::CALC_MIXED,
        ]);
    }
}
