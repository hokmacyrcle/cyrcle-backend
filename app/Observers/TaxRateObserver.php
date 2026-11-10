<?php

namespace App\Observers;

use App\Models\TaxRate;
use App\Services\AuditLogService;

class TaxRateObserver
{
    /**
     * Handle the TaxRate "created" event.
     */
    public function created(TaxRate $taxRate): void
    {
        AuditLogService::logCreated($taxRate, [
            'tax_name' => $taxRate->tax_name,
            'rate' => $taxRate->rate,
            'description' => $taxRate->description,
        ]);
    }

    /**
     * Handle the TaxRate "updated" event.
     */
    public function updated(TaxRate $taxRate): void
    {
        $changes = $taxRate->getChanges();
        $original = $taxRate->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($taxRate, $oldValues, $newValues);
        }
    }

    /**
     * Handle the TaxRate "deleted" event.
     */
    public function deleted(TaxRate $taxRate): void
    {
        AuditLogService::logDeleted($taxRate, [
            'tax_name' => $taxRate->tax_name,
            'rate' => $taxRate->rate,
            'description' => $taxRate->description,
        ]);
    }

    /**
     * Handle the TaxRate "restored" event.
     */
    public function restored(TaxRate $taxRate): void
    {
        AuditLogService::logRestored($taxRate);
    }

    /**
     * Handle the TaxRate "force deleted" event.
     */
    public function forceDeleted(TaxRate $taxRate): void
    {
        AuditLogService::logForceDeleted($taxRate, [
            'tax_name' => $taxRate->tax_name,
            'rate' => $taxRate->rate,
            'description' => $taxRate->description,
        ]);
    }
}
