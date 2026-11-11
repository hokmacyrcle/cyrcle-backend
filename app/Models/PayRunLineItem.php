<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayRunLineItem extends Model
{
    /** Earnings that are taxed and added to gross pay. */
    public const TAXABLE = ['allowance', 'bonus', 'overtime', 'leave'];
    /** What a person can add by hand. Leave items are created by the leave module. */
    public const MANUAL = ['allowance', 'bonus', 'overtime', 'reimbursement', 'deduction'];

    /** ATO allowance types for Single Touch Payroll (STP Phase 2 quick reference guide). */
    public const ALLOWANCE_TYPES = [
        'KN' => 'Task allowance', 'TD' => 'Tool allowance', 'LD' => 'Laundry allowance', 'QN' => 'Qualification or certificate allowance',
        'CD' => 'Cents per kilometre', 'RD' => 'Travel allowance', 'OD' => 'Other allowance',
    ];
    /** Extra description the ATO wants when the type is "other". */
    public const ALLOWANCE_DESCRIPTIONS = [
        'G1' => 'General', 'ND' => 'Non-deductible', 'V1' => 'Private vehicle', 'H1' => 'Home office equipment and internet', 'T1' => 'Fares',
    ];

    protected $guarded = [];
    protected $casts = ['counts_for_super' => 'boolean'];

    public function isTaxable(): bool
    {
        return in_array($this->kind, self::TAXABLE, true);
    }
}
