<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDesignation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'designation_id',
        'designation',
        'designation_start_date',
        'designation_end_date',
        'record_status',
    ];

    protected $casts = [
        'designation_start_date' => 'date',
        'designation_end_date' => 'date',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }
}
