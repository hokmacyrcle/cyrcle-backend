<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetEntry extends Model
{
    protected $guarded = [];
    protected $casts = ['work_date' => 'date', 'hours' => 'float'];
}
