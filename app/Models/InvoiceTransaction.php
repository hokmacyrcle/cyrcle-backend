<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceTransaction extends Model
{
    use HasFactory;

    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'invoice_id',
        'payment_id',
        'payment_amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    // Relationships
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Get attachments for this transaction
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'type_id', 'transaction_id')
                    ->where('type', 'Transaction');
    }

    /**
     * Get attachments count for this transaction
     */
    public function getAttachmentsCountAttribute()
    {
        return $this->attachments()->count();
    }

    // Note: payment_id references a payments table that may not exist yet
    // For now, we'll treat it as a simple identifier
}
