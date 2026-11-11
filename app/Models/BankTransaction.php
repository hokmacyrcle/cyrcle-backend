<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $table = 'bank_transactions';

    protected $fillable = [
        'company_id', 'bank_account_id', 'import_id', 'txn_date', 'description', 'amount', 'balance_after', 'status',
        'matched_invoice_id', 'invoice_payment_id', 'category_account_id', 'gst_code', 'note', 'fingerprint', 'source',
    ];

    protected $casts = ['txn_date' => 'date', 'amount' => 'decimal:2', 'balance_after' => 'decimal:2'];
}
