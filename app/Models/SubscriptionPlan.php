<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $primaryKey = 'plan_id';

    protected $fillable = [
        'plan_name',
        'description',
        'price',
        'billing_cycle',
        'billing_cycle_days',
        'max_users',
        'max_companies',
        'max_invoices_per_month',
        'max_quotes_per_month',
        'features',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'billing_cycle_days' => 'integer',
        'max_users' => 'integer',
        'max_companies' => 'integer',
        'max_invoices_per_month' => 'integer',
        'max_quotes_per_month' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class, 'subscription_id', 'plan_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    // Helper methods
    public function isUnlimited($field)
    {
        return is_null($this->$field);
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    public function getBillingCycleTextAttribute()
    {
        return ucfirst($this->billing_cycle);
    }
}
