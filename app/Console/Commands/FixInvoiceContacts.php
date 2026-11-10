<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Contact;

class FixInvoiceContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:fix-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix invoice contact relationships by updating sent_to field';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Fixing invoice contact relationships...');

        // Get all contacts
        $contacts = Contact::all();
        if ($contacts->isEmpty()) {
            $this->error('No contacts found in database!');
            return 1;
        }

        $this->info("Found {$contacts->count()} contacts");

        // Get all invoices
        $invoices = Invoice::all();
        if ($invoices->isEmpty()) {
            $this->info('No invoices found to fix.');
            return 0;
        }

        $this->info("Found {$invoices->count()} invoices");

        $fixedCount = 0;
        foreach ($invoices as $invoice) {
            // Check if sent_to is valid
            $contact = Contact::find($invoice->sent_to);
            
            if (!$contact) {
                // Fix by assigning to first available contact
                $firstContact = $contacts->first();
                $invoice->update(['sent_to' => $firstContact->contact_id]);
                
                $this->line("Fixed Invoice ID {$invoice->invoice_id}: sent_to {$invoice->sent_to} → {$firstContact->contact_id} ({$firstContact->contact_first_name} {$firstContact->contact_last_name})");
                $fixedCount++;
            } else {
                $this->line("Invoice ID {$invoice->invoice_id}: sent_to {$invoice->sent_to} ✓ ({$contact->contact_first_name} {$contact->contact_last_name})");
            }
        }

        $this->info("✅ Fixed {$fixedCount} invoice contact relationships!");
        
        // Test the API response
        $this->info('🧪 Testing API response...');
        $testInvoice = Invoice::with('contact')->first();
        if ($testInvoice && $testInvoice->contact) {
            $customerName = $this->getCustomerName($testInvoice->contact);
            $this->info("Sample customer name: {$customerName}");
        }

        return 0;
    }

    /**
     * Get customer name from contact (same logic as controller)
     */
    private function getCustomerName($contact): string
    {
        if (!$contact) {
            return 'Unknown Customer';
        }

        // Always prioritize contact person's name (first + last name)
        $firstName = $contact->contact_first_name ?? '';
        $lastName = $contact->contact_last_name ?? '';
        $personName = trim($firstName . ' ' . $lastName);
        
        // If we have a person name, return it
        if (!empty($personName)) {
            return $personName;
        }
        
        // Fallback to company name if no person name available
        if ($contact->contact_type === 'Company' && !empty($contact->contact_company_name)) {
            return $contact->contact_company_name;
        }
        
        return 'Unknown Customer';
    }
}
