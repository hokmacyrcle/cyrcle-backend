<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayRunLineItem extends Model
{
    /** Earnings that are taxed and added to gross pay. */
    public const TAXABLE = ['allowance', 'bonus', 'overtime', 'leave'];
    /** What a person can add by hand. Leave items are created by the leave module. */
    public const MANUAL = ['allowance', 'bonus', 'overtime', 'reimbursement', 'deduction'];

    protected $guarded = [];
    protected $casts = ['counts_for_super' => 'boolean'];

    public function isTaxable(): bool
    {
        return in_array($this->kind, self::TAXABLE, true);
    }
}
