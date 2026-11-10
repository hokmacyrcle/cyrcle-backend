<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteVersionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quote_version_id',
        'product_service',
        'description',
        'quantity',
        'rate',
        'amount',
        'gst',
        'gst_rate',
        'total',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'gst' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relationships
    public function quoteVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class, 'quote_version_id');
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'A$' . number_format($this->amount, 2);
    }

    public function getFormattedRateAttribute(): string
    {
        return 'A$' . number_format($this->rate, 2);
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'A$' . number_format($this->total, 2);
    }

    public function getFormattedGstAttribute(): string
    {
        return 'A$' . number_format($this->gst, 2);
    }

    // Methods
    public function calculateTotals(): void
    {
        $this->amount = $this->quantity * $this->rate;
        $this->gst = $this->amount * ($this->gst_rate / 100);
        $this->total = $this->amount + $this->gst;
    }

    public function getLineTotal(): float
    {
        return $this->total;
    }

    public function getSubtotal(): float
    {
        return $this->amount;
    }
}
