<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'invoice_id';

    protected $fillable = [
        'company_id',
        'invoice_date',
        'sent_to',
        'invoice_amount',
        'discount_type',
        'discount_method',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'gst_amount',
        'invoice_total',
        'invoice_due_date',
        'invoice_status',
        'invoice_payment_status',
        'invoice_paid_amount',
        'payment_term_id',
        'notes',
        'terms',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'invoice_due_date' => 'date',
        'invoice_amount' => 'decimal:2',
        'discount_type' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'invoice_total' => 'decimal:2',
        'invoice_paid_amount' => 'decimal:2',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'sent_to');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function transactions()
    {
        return $this->hasMany(InvoiceTransaction::class, 'invoice_id');
    }

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    /**
     * Get attachments for this invoice
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'type_id', 'invoice_id')
                    ->where('type', 'Invoice');
    }

    /**
     * Get attachments count for this invoice
     */
    public function getAttachmentsCountAttribute()
    {
        return $this->attachments()->count();
    }

    // Scopes
    public function scopeDue($query)
    {
        return $query->where('invoice_status', 'Due');
    }

    public function scopePastDue($query)
    {
        return $query->where('invoice_status', 'Past Due');
    }

    public function scopePaid($query)
    {
        return $query->where('invoice_payment_status', 'Paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('invoice_payment_status', 'Unpaid');
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('invoice_payment_status', 'Partially Paid');
    }
}
