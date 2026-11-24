<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $table = 'leave_applications';
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'hours' => 'float', 'decided_at' => 'datetime'];
}
