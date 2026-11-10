<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_id';

    protected $fillable = [
        'invoice_id',
        'item_description',
        'item_quantity',
        'item_rate',
        'item_cost',
        'item_gst',
        'item_total',
    ];

    protected $casts = [
        'item_quantity' => 'integer',
        'item_rate' => 'decimal:2',
        'item_cost' => 'decimal:2',
        'item_gst' => 'decimal:2',
        'item_total' => 'decimal:2',
    ];

    // Relationships
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    // Mutators
    public function setItemCostAttribute($value)
    {
        $this->attributes['item_cost'] = $this->item_quantity * $this->item_rate;
    }

    public function setItemGstAttribute($value)
    {
        $this->attributes['item_gst'] = $this->item_cost * 0.10; // 10% GST
    }

    public function setItemTotalAttribute($value)
    {
        $this->attributes['item_total'] = $this->item_cost + $this->item_gst;
    }
}
