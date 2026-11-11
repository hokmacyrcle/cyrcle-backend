<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetWeek extends Model
{
    protected $guarded = [];
    protected $casts = ['week_start' => 'date', 'approved_at' => 'datetime'];
}
