<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessBankAccount extends Model
{
    protected $table = 'business_bank_accounts';

    protected $fillable = ['company_id', 'name', 'account_type', 'bsb', 'account_number', 'active', 'last_imported_at', 'feed_provider', 'feed_reference'];

    protected $casts = ['active' => 'boolean', 'last_imported_at' => 'datetime'];

    public const TYPES = ['everyday' => 'Everyday', 'savings' => 'Savings', 'credit_card' => 'Credit card'];

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }
}
