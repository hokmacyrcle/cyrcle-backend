<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'pay_date' => 'date', 'super_due_date' => 'date',
        'finalised_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(PayRunLine::class, 'pay_run_id');
    }
}
