<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReceipt extends Model
{
    protected $table = 'bank_receipts';

    protected $fillable = [
        'company_id', 'uploaded_by', 'file_path', 'original_name', 'mime', 'size', 'description', 'receipt_date',
        'amount', 'gst', 'bank_account_id', 'transaction_id', 'category_account_id', 'scan_status',
    ];

    protected $casts = ['receipt_date' => 'date', 'amount' => 'decimal:2', 'gst' => 'decimal:2'];
}
