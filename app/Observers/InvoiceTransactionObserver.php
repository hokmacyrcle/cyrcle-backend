<?php

namespace App\Observers;

use App\Models\InvoiceTransaction;
use App\Services\AuditLogService;

class InvoiceTransactionObserver
{
    /**
     * Handle the InvoiceTransaction "created" event.
     */
    public function created(InvoiceTransaction $invoiceTransaction): void
    {
        AuditLogService::logCreated($invoiceTransaction, [
            'payment_amount' => $invoiceTransaction->payment_amount,
            'payment_date' => $invoiceTransaction->payment_date,
            'payment_method' => $invoiceTransaction->payment_method,
            'reference_number' => $invoiceTransaction->reference_number,
            'invoice_id' => $invoiceTransaction->invoice_id,
        ]);
    }

    /**
     * Handle the InvoiceTransaction "updated" event.
     */
    public function updated(InvoiceTransaction $invoiceTransaction): void
    {
        $changes = $invoiceTransaction->getChanges();
        $original = $invoiceTransaction->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($invoiceTransaction, $oldValues, $newValues);
        }
    }

    /**
     * Handle the InvoiceTransaction "deleted" event.
     */
    public function deleted(InvoiceTransaction $invoiceTransaction): void
    {
        AuditLogService::logDeleted($invoiceTransaction, [
            'payment_amount' => $invoiceTransaction->payment_amount,
            'payment_date' => $invoiceTransaction->payment_date,
            'payment_method' => $invoiceTransaction->payment_method,
            'reference_number' => $invoiceTransaction->reference_number,
            'invoice_id' => $invoiceTransaction->invoice_id,
        ]);
    }

    /**
     * Handle the InvoiceTransaction "restored" event.
     */
    public function restored(InvoiceTransaction $invoiceTransaction): void
    {
        AuditLogService::logRestored($invoiceTransaction);
    }

    /**
     * Handle the InvoiceTransaction "force deleted" event.
     */
    public function forceDeleted(InvoiceTransaction $invoiceTransaction): void
    {
        AuditLogService::logForceDeleted($invoiceTransaction, [
            'payment_amount' => $invoiceTransaction->payment_amount,
            'payment_date' => $invoiceTransaction->payment_date,
            'payment_method' => $invoiceTransaction->payment_method,
            'reference_number' => $invoiceTransaction->reference_number,
            'invoice_id' => $invoiceTransaction->invoice_id,
        ]);
    }
}
