<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PayTemplate extends Model
{
    use HasFactory;

    protected $primaryKey = 'pay_template_id';

    protected $fillable = [
        'employee_id',
        'template_name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DRAFT = 'draft';

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }

    public function earnings()
    {
        return $this->hasMany(PayTemplateEarning::class, 'pay_template_id', 'pay_template_id')
                    ->orderBy('display_order', 'asc');
    }

    public function activeEarnings()
    {
        return $this->hasMany(PayTemplateEarning::class, 'pay_template_id', 'pay_template_id')
                    ->where('is_active', true)
                    ->orderBy('display_order', 'asc');
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

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Accessors
    public function getTotalEarningsAttribute()
    {
        return $this->activeEarnings->sum('total');
    }

    public function getTotalHoursAttribute()
    {
        return $this->activeEarnings->where('calculation_type', 'hours_rate')->sum('hours');
    }

    public function getTotalFixedAmountsAttribute()
    {
        return $this->activeEarnings->where('calculation_type', 'fixed_amount')->sum('fixed_amount');
    }

    public function getEarningsCountAttribute()
    {
        return $this->activeEarnings->count();
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    public function getIsActiveAttribute()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getIsDraftAttribute()
    {
        return $this->status === self::STATUS_DRAFT;
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

    public function setAsDraft()
    {
        $this->update(['status' => self::STATUS_DRAFT]);
        return $this;
    }

    public function addEarning($data)
    {
        $data['pay_template_id'] = $this->pay_template_id;
        $data['display_order'] = $this->earnings()->max('display_order') + 1;
        
        return PayTemplateEarning::create($data);
    }

    public function removeEarning($earningsId)
    {
        $earning = $this->earnings()->find($earningsId);
        if ($earning) {
            $earning->delete();
            $this->reorderEarnings();
            return true;
        }
        return false;
    }

    public function reorderEarnings()
    {
        $earnings = $this->earnings()->orderBy('display_order', 'asc')->get();
        foreach ($earnings as $index => $earning) {
            $earning->update(['display_order' => $index]);
        }
    }

    public function calculateTotals()
    {
        foreach ($this->earnings as $earning) {
            $earning->calculateTotal();
        }
        return $this;
    }

    public function duplicate($newEmployeeId = null)
    {
        $newTemplate = $this->replicate();
        $newTemplate->employee_id = $newEmployeeId ?? $this->employee_id;
        $newTemplate->template_name = $this->template_name . ' (Copy)';
        $newTemplate->status = self::STATUS_DRAFT;
        $newTemplate->created_by = auth()->id();
        $newTemplate->updated_by = auth()->id();
        $newTemplate->save();

        // Duplicate earnings
        foreach ($this->earnings as $earning) {
            $newEarning = $earning->replicate();
            $newEarning->pay_template_id = $newTemplate->pay_template_id;
            $newEarning->created_by = auth()->id();
            $newEarning->updated_by = auth()->id();
            $newEarning->save();
        }

        return $newTemplate;
    }

    // Static methods
    public static function getByEmployee($employeeId)
    {
        return self::byEmployee($employeeId)->with('earnings')->get();
    }

    public static function getActiveByEmployee($employeeId)
    {
        return self::byEmployee($employeeId)->active()->with('activeEarnings')->first();
    }

    public static function getTemplateStatistics()
    {
        return [
            'total_templates' => self::count(),
            'active_templates' => self::active()->count(),
            'inactive_templates' => self::inactive()->count(),
            'draft_templates' => self::draft()->count(),
            'templates_with_earnings' => self::whereHas('earnings')->count(),
        ];
    }
}
