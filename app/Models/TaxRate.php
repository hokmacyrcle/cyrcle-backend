<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tax_name',
        'agency_name',
        'rate',
        'type',
        'is_active',
        'description',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSingle($query)
    {
        return $query->where('type', 'single');
    }

    public function scopeCombined($query)
    {
        return $query->where('type', 'combined');
    }

    // Helper methods
    public function getFormattedRateAttribute()
    {
        return $this->rate . '%';
    }

    public function getDisplayNameAttribute()
    {
        return $this->tax_name . ' (' . $this->getFormattedRateAttribute() . ')';
    }
}
