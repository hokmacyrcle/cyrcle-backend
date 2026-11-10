<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\AuditLogService;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        AuditLogService::logCreated($employee, [
            'employee_name' => $employee->emp_first_name . ' ' . $employee->emp_last_name,
            'employee_email' => $employee->emp_email_address,
            'employee_phone' => $employee->emp_phone_number,
            'designation' => $employee->currentDesignation?->designation_name,
            'company' => $employee->company?->company_name,
        ]);
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        $changes = $employee->getChanges();
        $original = $employee->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($employee, $oldValues, $newValues);
        }
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        AuditLogService::logDeleted($employee, [
            'employee_name' => $employee->emp_first_name . ' ' . $employee->emp_last_name,
            'employee_email' => $employee->emp_email_address,
            'employee_phone' => $employee->emp_phone_number,
            'designation' => $employee->currentDesignation?->designation_name,
            'company' => $employee->company?->company_name,
        ]);
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        AuditLogService::logRestored($employee);
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        AuditLogService::logForceDeleted($employee, [
            'employee_name' => $employee->emp_first_name . ' ' . $employee->emp_last_name,
            'employee_email' => $employee->emp_email_address,
            'employee_phone' => $employee->emp_phone_number,
            'designation' => $employee->currentDesignation?->designation_name,
            'company' => $employee->company->company_name,
        ]);
    }
}
