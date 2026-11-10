<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class PaymentTerm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'due_calculation_method',
        'fixed_days_value',
        'certain_day_value',
        'next_month_threshold_days',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fixed_days_value' => 'integer',
        'certain_day_value' => 'integer',
        'next_month_threshold_days' => 'integer',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper methods
    public function calculateDueDate($invoiceDate = null)
    {
        $invoiceDate = $invoiceDate ? Carbon::parse($invoiceDate) : Carbon::now();
        
        if ($this->due_calculation_method === 'fixed_days') {
            return $invoiceDate->copy()->addDays($this->fixed_days_value);
        } elseif ($this->due_calculation_method === 'certain_day') {
            $dueDate = $invoiceDate->copy()->setDay($this->certain_day_value);
            
            // Check if we need to move to next month
            if ($this->next_month_threshold_days && 
                $invoiceDate->diffInDays($dueDate) <= $this->next_month_threshold_days) {
                $dueDate->addMonth();
            }
            
            return $dueDate;
        }
        
        return $invoiceDate;
    }

    public function getDisplayNameAttribute()
    {
        if ($this->due_calculation_method === 'fixed_days') {
            return $this->name . ' (' . $this->fixed_days_value . ' days)';
        } elseif ($this->due_calculation_method === 'certain_day') {
            return $this->name . ' (Day ' . $this->certain_day_value . ')';
        }
        
        return $this->name;
    }
}
