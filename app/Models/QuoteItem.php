<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_id';

    protected $fillable = [
        'quote_id',
        'product_service',
        'description',
        'quantity',
        'rate',
        'amount',
        'gst',
        'gst_rate',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'gst' => 'decimal:2',
    ];

    // Relationships
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
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

    public function getFormattedGstAttribute(): string
    {
        return 'A$' . number_format($this->gst, 2);
    }

    // Methods
    public function calculateAmount(): void
    {
        $this->amount = $this->quantity * $this->rate;
        $this->save();
    }

    public function calculateGst(): void
    {
        // Assuming 10% GST rate
        $this->gst = $this->amount * 0.10;
        $this->save();
    }
}
