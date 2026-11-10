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
        'item_gst_rate',
    ];

    protected $casts = [
        'item_quantity' => 'decimal:2',
        'item_gst_rate' => 'decimal:2',
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
}
