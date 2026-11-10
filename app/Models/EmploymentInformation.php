<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmploymentInformation extends Model
{
    use HasFactory;

    protected $primaryKey = 'employment_info_id';

    protected $fillable = [
        'employee_id',
        'employment_type',
        'income_type',
        'employment_basis',
                'start_date',
        'classification',
        'pay_frequency',
        'employee_group',
        'holiday_group',
        'include_holidays_in_payslips',
        'ordinary_earnings_rate',
        'authorised_to_approve_leave',
        'authorised_to_approve_timesheets',
        'authorised_fields',
        'superannuation_memberships',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'include_holidays_in_payslips' => 'boolean',
        'authorised_to_approve_leave' => 'boolean',
        'authorised_to_approve_timesheets' => 'boolean',
        'superannuation_memberships' => 'array',
        'is_active' => 'boolean',
    ];

    // Employment type constants
    const TYPE_EMPLOYEE = 'Employee';
    const TYPE_CONTRACTOR = 'Contractor';

    // Employment basis constants
    const BASIS_FULL_TIME = 'Full-time';
    const BASIS_PART_TIME = 'Part-time';
    const BASIS_CASUAL = 'Casual';
    const BASIS_FIXED_TERM = 'Fixed-term';
    const BASIS_SEASONAL = 'Seasonal';

    // Income type constants
    const INCOME_SALARY_WAGES = 'Salary and wages';
    const INCOME_COMMISSION = 'Commission';
    const INCOME_BONUS = 'Bonus';
    const INCOME_OVERTIME = 'Overtime';
    const INCOME_ALLOWANCE = 'Allowance';

    // Pay frequency constants
    const FREQUENCY_WEEKLY = 'Weekly';
    const FREQUENCY_FORTNIGHTLY = 'Fortnightly Calendar';
    const FREQUENCY_MONTHLY = 'Monthly';
    const FREQUENCY_QUARTERLY = 'Quarterly';
    const FREQUENCY_ANNUALLY = 'Annually';

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEmployees($query)
    {
        return $query->where('employment_type', self::TYPE_EMPLOYEE);
    }

    public function scopeContractors($query)
    {
        return $query->where('employment_type', self::TYPE_CONTRACTOR);
    }

    public function scopeFullTime($query)
    {
        return $query->where('employment_basis', self::BASIS_FULL_TIME);
    }

    public function scopePartTime($query)
    {
        return $query->where('employment_basis', self::BASIS_PART_TIME);
    }

    public function scopeByGroup($query, $group)
    {
        return $query->where('employee_group', $group);
    }

    public function scopeByHolidayGroup($query, $group)
    {
        return $query->where('holiday_group', $group);
    }

    // Accessors
    public function getYearsOfServiceAttribute()
    {
        if ($this->start_date) {
            return Carbon::now()->diffInYears($this->start_date);
        }
        return 0;
    }

    public function getIsEmployeeAttribute()
    {
        return $this->employment_type === self::TYPE_EMPLOYEE;
    }

    public function getIsContractorAttribute()
    {
        return $this->employment_type === self::TYPE_CONTRACTOR;
    }

    public function getIsFullTimeAttribute()
    {
        return $this->employment_basis === self::BASIS_FULL_TIME;
    }

    public function getHasLeaveApprovalAttribute()
    {
        return $this->authorised_to_approve_leave;
    }

    public function getHasTimesheetApprovalAttribute()
    {
        return $this->authorised_to_approve_timesheets;
    }

    public function getSuperannuationFundsAttribute()
    {
        return $this->superannuation_memberships ?? [];
    }

    public function getAuthorizedFieldsArrayAttribute()
    {
        if (empty($this->authorised_fields)) {
            return [];
        }

        // Try to decode as JSON first
        $decoded = json_decode($this->authorised_fields, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // If not JSON, treat as comma-separated
        return array_map('trim', explode(',', $this->authorised_fields));
    }

    public function getAuthorizedFieldsStringAttribute()
    {
        if (empty($this->authorised_fields)) {
            return '';
        }

        // If it's already a string, return as is
        if (is_string($this->authorised_fields)) {
            return $this->authorised_fields;
        }

        // If it's an array, convert to comma-separated string
        if (is_array($this->authorised_fields)) {
            return implode(', ', $this->authorised_fields);
        }

        return '';
    }

    // Methods
    public function addSuperannuationFund($fundName, $memberNumber, $fundCode = null)
    {
        $funds = $this->superannuation_memberships ?? [];
        
        $funds[] = [
            'fund_name' => $fundName,
            'member_number' => $memberNumber,
            'fund_code' => $fundCode,
            'added_at' => now()->toISOString(),
        ];
        
        $this->update(['superannuation_memberships' => $funds]);
    }

    public function removeSuperannuationFund($memberNumber)
    {
        $funds = $this->superannuation_memberships ?? [];
        
        $funds = array_filter($funds, function($fund) use ($memberNumber) {
            return $fund['member_number'] !== $memberNumber;
        });
        
        $this->update(['superannuation_memberships' => array_values($funds)]);
    }

    public function updateSuperannuationFund($memberNumber, $newData)
    {
        $funds = $this->superannuation_memberships ?? [];
        
        foreach ($funds as &$fund) {
            if ($fund['member_number'] === $memberNumber) {
                $fund = array_merge($fund, $newData, ['updated_at' => now()->toISOString()]);
                break;
            }
        }
        
        $this->update(['superannuation_memberships' => $funds]);
    }

    public function hasSuperannuationFund($memberNumber)
    {
        $funds = $this->superannuation_memberships ?? [];
        
        foreach ($funds as $fund) {
            if ($fund['member_number'] === $memberNumber) {
                return true;
            }
        }
        
        return false;
    }

    public function getSuperannuationFund($memberNumber)
    {
        $funds = $this->superannuation_memberships ?? [];
        
        foreach ($funds as $fund) {
            if ($fund['member_number'] === $memberNumber) {
                return $fund;
            }
        }
        
        return null;
    }

    // Authorized Fields Management Methods
    public function setAuthorizedFields($fields, $format = 'json')
    {
        if ($format === 'json') {
            $this->authorised_fields = json_encode($fields);
        } else {
            // Comma-separated format
            if (is_array($fields)) {
                $this->authorised_fields = implode(', ', $fields);
            } else {
                $this->authorised_fields = $fields;
            }
        }
        
        $this->save();
    }

    public function addAuthorizedField($fieldName)
    {
        $fields = $this->authorized_fields_array;
        
        if (!in_array($fieldName, $fields)) {
            $fields[] = $fieldName;
            $this->setAuthorizedFields($fields);
        }
        
        return $this;
    }

    public function removeAuthorizedField($fieldName)
    {
        $fields = $this->authorized_fields_array;
        
        $fields = array_filter($fields, function($field) use ($fieldName) {
            return $field !== $fieldName;
        });
        
        $this->setAuthorizedFields($fields);
        return $this;
    }

    public function hasAuthorizedField($fieldName)
    {
        $fields = $this->authorized_fields_array;
        return in_array($fieldName, $fields);
    }

    public function getAuthorizedFieldsCountAttribute()
    {
        return count($this->authorized_fields_array);
    }

    public function clearAuthorizedFields()
    {
        $this->authorised_fields = null;
        $this->save();
        return $this;
    }

    // Static methods for common queries
    public static function getEmployeesByGroup($group)
    {
        return self::byGroup($group)->with('employee')->get();
    }

    public static function getEmployeesByHolidayGroup($group)
    {
        return self::byHolidayGroup($group)->with('employee')->get();
    }

    public static function getEmployeesWithLeaveApproval()
    {
        return self::where('authorised_to_approve_leave', true)
                   ->with('employee')
                   ->get();
    }

    public static function getEmployeesWithTimesheetApproval()
    {
        return self::where('authorised_to_approve_timesheets', true)
                   ->with('employee')
                   ->get();
    }

    public static function getEmploymentStatistics()
    {
        return [
            'total_employees' => self::employees()->count(),
            'total_contractors' => self::contractors()->count(),
            'full_time' => self::fullTime()->count(),
            'part_time' => self::partTime()->count(),
            'with_leave_approval' => self::where('authorised_to_approve_leave', true)->count(),
            'with_timesheet_approval' => self::where('authorised_to_approve_timesheets', true)->count(),
        ];
    }
}
