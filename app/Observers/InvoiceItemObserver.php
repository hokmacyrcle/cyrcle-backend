<?php

namespace App\Observers;

use App\Models\InvoiceItem;
use App\Services\AuditLogService;

class InvoiceItemObserver
{
    /**
     * Handle the InvoiceItem "created" event.
     */
    public function created(InvoiceItem $invoiceItem): void
    {
        AuditLogService::logCreated($invoiceItem, [
            'item_name' => $invoiceItem->item_name,
            'quantity' => $invoiceItem->quantity,
            'unit_price' => $invoiceItem->unit_price,
            'total_amount' => $invoiceItem->total_amount,
            'invoice_id' => $invoiceItem->invoice_id,
        ]);
    }

    /**
     * Handle the InvoiceItem "updated" event.
     */
    public function updated(InvoiceItem $invoiceItem): void
    {
        $changes = $invoiceItem->getChanges();
        $original = $invoiceItem->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($invoiceItem, $oldValues, $newValues);
        }
    }

    /**
     * Handle the InvoiceItem "deleted" event.
     */
    public function deleted(InvoiceItem $invoiceItem): void
    {
        AuditLogService::logDeleted($invoiceItem, [
            'item_name' => $invoiceItem->item_name,
            'quantity' => $invoiceItem->quantity,
            'unit_price' => $invoiceItem->unit_price,
            'total_amount' => $invoiceItem->total_amount,
            'invoice_id' => $invoiceItem->invoice_id,
        ]);
    }

    /**
     * Handle the InvoiceItem "restored" event.
     */
    public function restored(InvoiceItem $invoiceItem): void
    {
        AuditLogService::logRestored($invoiceItem);
    }

    /**
     * Handle the InvoiceItem "force deleted" event.
     */
    public function forceDeleted(InvoiceItem $invoiceItem): void
    {
        AuditLogService::logForceDeleted($invoiceItem, [
            'item_name' => $invoiceItem->item_name,
            'quantity' => $invoiceItem->quantity,
            'unit_price' => $invoiceItem->unit_price,
            'total_amount' => $invoiceItem->total_amount,
            'invoice_id' => $invoiceItem->invoice_id,
        ]);
    }
}
