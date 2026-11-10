<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\AuditLogService;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        AuditLogService::logCreated($invoice, [
            'invoice_number' => $invoice->invoice_id,
            'company' => $invoice->company?->company_name,
            'contact' => $invoice->contact?->contact_name,
            'amount' => $invoice->invoice_total,
            'status' => $invoice->invoice_status,
        ]);
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        $changes = $invoice->getChanges();
        $original = $invoice->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($invoice, $oldValues, $newValues);
        }
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        AuditLogService::logDeleted($invoice, [
            'invoice_number' => $invoice->invoice_id,
            'company' => $invoice->company?->company_name,
            'contact' => $invoice->contact?->contact_name,
            'amount' => $invoice->invoice_total,
            'status' => $invoice->invoice_status,
        ]);
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        AuditLogService::logRestored($invoice);
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        AuditLogService::logForceDeleted($invoice, [
            'invoice_number' => $invoice->invoice_id,
            'company' => $invoice->company?->company_name,
            'contact' => $invoice->contact?->contact_name,
            'amount' => $invoice->invoice_total,
            'status' => $invoice->invoice_status,
        ]);
    }
}
