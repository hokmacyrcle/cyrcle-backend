<?php

namespace App\Observers;

use App\Models\PaymentTerm;
use App\Services\AuditLogService;

class PaymentTermObserver
{
    /**
     * Handle the PaymentTerm "created" event.
     */
    public function created(PaymentTerm $paymentTerm): void
    {
        AuditLogService::logCreated($paymentTerm, [
            'name' => $paymentTerm->name,
            'due_calculation_method' => $paymentTerm->due_calculation_method,
            'fixed_days_value' => $paymentTerm->fixed_days_value,
        ]);
    }

    /**
     * Handle the PaymentTerm "updated" event.
     */
    public function updated(PaymentTerm $paymentTerm): void
    {
        $changes = $paymentTerm->getChanges();
        $original = $paymentTerm->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($paymentTerm, $oldValues, $newValues);
        }
    }

    /**
     * Handle the PaymentTerm "deleted" event.
     */
    public function deleted(PaymentTerm $paymentTerm): void
    {
        AuditLogService::logDeleted($paymentTerm, [
            'name' => $paymentTerm->name,
            'due_calculation_method' => $paymentTerm->due_calculation_method,
            'fixed_days_value' => $paymentTerm->fixed_days_value,
        ]);
    }

    /**
     * Handle the PaymentTerm "restored" event.
     */
    public function restored(PaymentTerm $paymentTerm): void
    {
        AuditLogService::logRestored($paymentTerm);
    }

    /**
     * Handle the PaymentTerm "force deleted" event.
     */
    public function forceDeleted(PaymentTerm $paymentTerm): void
    {
        AuditLogService::logForceDeleted($paymentTerm, [
            'name' => $paymentTerm->name,
            'due_calculation_method' => $paymentTerm->due_calculation_method,
            'fixed_days_value' => $paymentTerm->fixed_days_value,
        ]);
    }
}
