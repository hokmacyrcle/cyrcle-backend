<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperannuationFund extends Model
{
    use HasFactory;

    protected $table = 'emp_superannuation_funds';
    protected $primaryKey = 'superannuation_fund_id';

    protected $fillable = [
        'employee_id',
        'fund_name',
        'fund_code',
        'member_number',
        'usi',
        'contribution_type',
        'contribution_value',
        'is_statutory_rate',
        'status',
        'effective_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'contribution_value' => 'decimal:2',
        'is_statutory_rate' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the employee that owns the superannuation fund.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }

    /**
     * Get the user who created the superannuation fund.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the superannuation fund.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include active funds.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include funds for a specific employee.
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Check if the fund is using statutory rate.
     */
    public function isStatutoryRate(): bool
    {
        return $this->is_statutory_rate || $this->contribution_type === 'statutory_rate';
    }

    /**
     * Get the display value for the contribution.
     */
    public function getContributionDisplayAttribute(): string
    {
        if ($this->isStatutoryRate()) {
            return 'Statutory Rate';
        }

        if ($this->contribution_type === 'percentage') {
            return number_format($this->contribution_value, 2) . '%';
        }

        if ($this->contribution_type === 'amount') {
            return '$' . number_format($this->contribution_value, 2);
        }

        return 'N/A';
    }
}

