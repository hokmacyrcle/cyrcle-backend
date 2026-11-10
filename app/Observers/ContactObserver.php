<?php

namespace App\Observers;

use App\Models\Contact;
use App\Services\AuditLogService;

class ContactObserver
{
    /**
     * Handle the Contact "created" event.
     */
    public function created(Contact $contact): void
    {
        AuditLogService::logCreated($contact, [
            'contact_name' => $contact->full_name,
            'contact_email' => $contact->contact_email_address,
            'contact_phone' => $contact->contact_phone_number,
            'company' => $contact->company?->company_name,
        ]);
    }

    /**
     * Handle the Contact "updated" event.
     */
    public function updated(Contact $contact): void
    {
        $changes = $contact->getChanges();
        $original = $contact->getOriginal();
        
        // Only log if there are actual changes
        if (!empty($changes)) {
            $oldValues = array_intersect_key($original, $changes);
            $newValues = $changes;
            
            AuditLogService::logUpdated($contact, $oldValues, $newValues);
        }
    }

    /**
     * Handle the Contact "deleted" event.
     */
    public function deleted(Contact $contact): void
    {
        AuditLogService::logDeleted($contact, [
            'contact_name' => $contact->full_name,
            'contact_email' => $contact->contact_email_address,
            'contact_phone' => $contact->contact_phone_number,
            'company' => $contact->company?->company_name,
        ]);
    }

    /**
     * Handle the Contact "restored" event.
     */
    public function restored(Contact $contact): void
    {
        AuditLogService::logRestored($contact);
    }

    /**
     * Handle the Contact "force deleted" event.
     */
    public function forceDeleted(Contact $contact): void
    {
        AuditLogService::logForceDeleted($contact, [
            'contact_name' => $contact->full_name,
            'contact_email' => $contact->contact_email_address,
            'contact_phone' => $contact->contact_phone_number,
            'company' => $contact->company?->company_name,
        ]);
    }
}
