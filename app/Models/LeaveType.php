<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A kind of leave a business offers. Each business gets the National Employment Standards defaults on first use. */
class LeaveType extends Model
{
    protected $guarded = [];
    protected $casts = ['paid' => 'boolean', 'accrual_hours' => 'float'];

    /** Annual leave builds up 1 hour per 13 hours worked (4 weeks a year); personal/carer's leave 1 per 26 (10 days a year). */
    public const DEFAULTS = [
        ['code' => 'annual', 'name' => 'Annual leave', 'paid' => true, 'accrual_hours' => 13],
        ['code' => 'personal', 'name' => "Personal/carer's leave", 'paid' => true, 'accrual_hours' => 26],
        ['code' => 'unpaid', 'name' => 'Unpaid leave', 'paid' => false, 'accrual_hours' => null],
    ];
}
