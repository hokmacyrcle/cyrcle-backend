<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'type_id',
        'image_url',
        'original_name',
        'filename',
        'size',
        'mime_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Get the quote that owns the attachment.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'type_id')->where('type', 'Quote');
    }

    /**
     * Get the invoice that owns the attachment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'type_id')->where('type', 'Invoice');
    }

    /**
     * Get the invoice transaction that owns the attachment.
     */
    public function invoiceTransaction(): BelongsTo
    {
        return $this->belongsTo(InvoiceTransaction::class, 'type_id')->where('type', 'Transaction');
    }

    /**
     * Get the company that owns the attachment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'type_id')->where('type', 'Company');
    }

    /**
     * Get the bill that owns the attachment.
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'type_id')->where('type', 'Bill');
    }

    /**
     * Get the employee that owns the attachment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'type_id')->where('type', 'Employee');
    }

    /**
     * Get the polymorphic relationship based on type.
     */
    public function attachable()
    {
        return $this->morphTo('attachable', 'type', 'type_id');
    }
}
