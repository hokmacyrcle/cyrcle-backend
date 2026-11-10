<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $primaryKey = 'bill_id';

    protected $fillable = [
        'bill_number',
        'bill_date',
        'due_date',
        'vendor_name',
        'vendor_email',
        'vendor_phone',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'notes',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get attachments for this bill
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'type_id', 'bill_id')
                    ->where('type', 'Bill');
    }

    /**
     * Get attachments count for this bill
     */
    public function getAttachmentsCountAttribute()
    {
        return $this->attachments()->count();
    }
}



