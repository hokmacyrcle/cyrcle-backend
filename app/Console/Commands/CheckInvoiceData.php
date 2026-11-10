<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Contact;

class CheckInvoiceData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:check-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check invoice data to identify why some invoices are not showing up';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking Invoice Data...');

        // Get all invoices
        $allInvoices = Invoice::all();
        $this->info("Total invoices in database: {$allInvoices->count()}");

        // Check each invoice
        foreach ($allInvoices as $invoice) {
            $this->line("\n📄 Invoice ID: {$invoice->invoice_id}");
            $this->line("   sent_to: {$invoice->sent_to}");
            $this->line("   company_id: {$invoice->company_id}");
            $this->line("   invoice_date: {$invoice->invoice_date}");
            $this->line("   invoice_total: {$invoice->invoice_total}");
            $this->line("   invoice_status: {$invoice->invoice_status}");
            $this->line("   invoice_payment_status: {$invoice->invoice_payment_status}");
            
            // Check if contact exists
            $contact = Contact::find($invoice->sent_to);
            if ($contact) {
                $this->line("   ✅ Contact found: {$contact->contact_first_name} {$contact->contact_last_name}");
            } else {
                $this->error("   ❌ Contact NOT found for sent_to: {$invoice->sent_to}");
            }
        }

        // Test the API query
        $this->info("\n🧪 Testing API Query...");
        $query = Invoice::select([
            'invoices.invoice_id',
            'invoices.invoice_date',
            'invoices.invoice_total',
            'invoices.invoice_status',
            'invoices.invoice_payment_status',
            'invoices.invoice_due_date',
            'invoices.sent_to'
        ])
        ->with(['contact:contact_id,contact_first_name,contact_last_name,contact_company_name,contact_type']);

        $invoices = $query->get();
        $this->info("Invoices returned by API query: {$invoices->count()}");

        foreach ($invoices as $invoice) {
            $this->line("   Invoice ID: {$invoice->invoice_id} - Contact: " . ($invoice->contact ? 'Found' : 'NULL'));
        }

        // Check for any invoices with missing contact relationships
        $this->info("\n🔍 Checking for orphaned invoices...");
        $orphanedInvoices = Invoice::whereNotIn('sent_to', function($query) {
            $query->select('contact_id')->from('contacts');
        })->get();

        if ($orphanedInvoices->count() > 0) {
            $this->warn("Found {$orphanedInvoices->count()} invoices with invalid sent_to values:");
            foreach ($orphanedInvoices as $invoice) {
                $this->line("   Invoice ID: {$invoice->invoice_id} - sent_to: {$invoice->sent_to}");
            }
        } else {
            $this->info("✅ All invoices have valid contact relationships");
        }

        return 0;
    }
}
