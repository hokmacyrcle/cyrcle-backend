<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\AuditLogService;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     */
    public function created(Company $company): void
    {
        AuditLogService::logCreated($company, [
            'company_name' => $company->company_name,
            'company_type' => $company->company_type,
            'company_abn' => $company->company_abn,
        ]);
    }

    /**
     * Handle the Company "updated" event.
     */
    public function updated(Company $company): void
    {
        $changes = $company->getChanges();
        $original = $company->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($company, $oldValues, $newValues);
        }
    }

    /**
     * Handle the Company "deleted" event.
     */
    public function deleted(Company $company): void
    {
        AuditLogService::logDeleted($company, [
            'company_name' => $company->company_name,
            'company_type' => $company->company_type,
            'company_abn' => $company->company_abn,
        ]);
    }

    /**
     * Handle the Company "restored" event.
     */
    public function restored(Company $company): void
    {
        AuditLogService::logRestored($company);
    }

    /**
     * Handle the Company "force deleted" event.
     */
    public function forceDeleted(Company $company): void
    {
        AuditLogService::logForceDeleted($company, [
            'company_name' => $company->company_name,
            'company_type' => $company->company_type,
            'company_abn' => $company->company_abn,
        ]);
    }
}
