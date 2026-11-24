<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayRunLine extends Model
{
    protected $guarded = [];

    /** True while a person still has to enter or check something on this line before the run can be finalised. */
    public function needsAttention(): bool
    {
        $hours = $this->pay_basis === 'hourly' && ($this->hours === null || !$this->hours_confirmed);
        $tax = $this->tax_status !== 'auto' && !$this->tax_confirmed;
        return $hours || $tax;
    }

    protected $casts = [
        'tax_overridden' => 'boolean', 'tax_confirmed' => 'boolean', 'hours_confirmed' => 'boolean',
    ];
}
