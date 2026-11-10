<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpAssignLeaveType extends Model
{
    use HasFactory;

    protected $table = 'emp_assign_leave_type';

    protected $fillable = [
        'leave_type_id',
        'leave_cat',
        'leave_calculation_method_id',
        'hours_accured_annually',
        'openning_balance',
        'hours_employee_works_fortnightly',
        'termination_unused_balnce',
        'employee_id',
        'created_by',
        'updated_by',
        'company_id',
    ];

    protected $casts = [
        'hours_accured_annually' => 'decimal:2',
        'openning_balance' => 'decimal:2',
        'hours_employee_works_fortnightly' => 'decimal:2',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
