<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveEntry extends Model
{
    protected $guarded = [];
    protected $casts = ['entry_date' => 'date', 'hours' => 'float'];
}
