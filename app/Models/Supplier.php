<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $primaryKey = 'supplier_id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'currency_id',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'supplier_display_name',
        'company_name',
        'email',
        'phone_no',
        'mobile_number',
        'fax',
        'other',
        'website',
        'street_address1',
        'street_address_2',
        'city_state',
        'postcode',
        'country',
        'note',
        'taxes_number',
        'expense_rates_billing_rate',
        'payment_terms_id',
        'account_no',
        'create_batch_payments',
        'bsb',
        'bank_account_name',
        'bank_account_number',
        'statement_text',
        'default_expense_cat_id',
        'opening_balance',
        'opening_balance_date',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expense_rates_billing_rate' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'create_batch_payments' => 'string',
    ];

    /**
     * Get the company that owns the supplier.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the currency for the supplier.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * Get the payment terms for the supplier.
     */
    public function paymentTerms()
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_terms_id');
    }

    /**
     * Get the default expense category for the supplier.
     */
    public function defaultExpenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, 'default_expense_cat_id');
    }

    /**
     * Get the user who created the supplier.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the supplier.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
