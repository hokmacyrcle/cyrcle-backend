<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $primaryKey = 'account_id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'account_name',
        'account_type',
        'detail_type',
        'subaccount',
        'parent_account',
        'description',
        'company_id',
    ];

    protected $casts = [
        'account_type' => 'integer',
        'detail_type' => 'integer',
        'parent_account' => 'integer',
        'company_id' => 'integer',
        'subaccount' => 'string',
    ];

    /**
     * Get the company that owns the account.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the parent account.
     */
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_account', 'account_id');
    }

    /**
     * Get the child accounts.
     */
    public function children()
    {
        return $this->hasMany(Account::class, 'parent_account', 'account_id');
    }

    /**
     * Get the account type dropdown.
     */
    public function accountType()
    {
        return $this->belongsTo(AccountDropdown::class, 'account_type');
    }

    /**
     * Get the detail type dropdown.
     */
    public function detailType()
    {
        return $this->belongsTo(AccountDropdown::class, 'detail_type');
    }
}
