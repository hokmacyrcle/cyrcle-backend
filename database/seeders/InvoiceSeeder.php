<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\Contact;
use Carbon\Carbon;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing companies and contacts
        $companies = Company::all();
        $contacts = Contact::all();

        if ($companies->isEmpty() || $contacts->isEmpty()) {
            $this->command->warn('No companies or contacts found. Please run CompanySeeder and ContactSeeder first.');
            return;
        }

        $invoiceData = [
            [
                'company_id' => $companies->first()->company_id,
                'invoice_date' => Carbon::now()->subDays(30),
                'sent_to' => $contacts->first()->contact_id,
                'invoice_amount' => 1500.00,
                'gst_amount' => 150.00,
                'invoice_total' => 1650.00,
                'invoice_due_date' => Carbon::now()->addDays(15),
                'invoice_status' => 'Due',
                'invoice_payment_status' => 'Unpaid',
                'invoice_paid_amount' => 0.00,
                'invoice_attachment' => 'invoices/inv-001.pdf',
            ],
            [
                'company_id' => $companies->first()->company_id,
                'invoice_date' => Carbon::now()->subDays(45),
                'sent_to' => $contacts->first()->contact_id,
                'invoice_amount' => 2500.00,
                'gst_amount' => 250.00,
                'invoice_total' => 2750.00,
                'invoice_due_date' => Carbon::now()->subDays(5),
                'invoice_status' => 'Past Due',
                'invoice_payment_status' => 'Unpaid',
                'invoice_paid_amount' => 0.00,
                'invoice_attachment' => 'invoices/inv-002.pdf',
            ],
            [
                'company_id' => $companies->first()->company_id,
                'invoice_date' => Carbon::now()->subDays(60),
                'sent_to' => $contacts->first()->contact_id,
                'invoice_amount' => 800.00,
                'gst_amount' => 80.00,
                'invoice_total' => 880.00,
                'invoice_due_date' => Carbon::now()->subDays(20),
                'invoice_status' => 'Past Due',
                'invoice_payment_status' => 'Paid',
                'invoice_paid_amount' => 880.00,
                'invoice_attachment' => 'invoices/inv-003.pdf',
            ],
            [
                'company_id' => $companies->first()->company_id,
                'invoice_date' => Carbon::now()->subDays(15),
                'sent_to' => $contacts->first()->contact_id,
                'invoice_amount' => 3200.00,
                'gst_amount' => 320.00,
                'invoice_total' => 3520.00,
                'invoice_due_date' => Carbon::now()->addDays(30),
                'invoice_status' => 'Due',
                'invoice_payment_status' => 'Partially Paid',
                'invoice_paid_amount' => 1760.00,
                'invoice_attachment' => 'invoices/inv-004.pdf',
            ],
            [
                'company_id' => $companies->first()->company_id,
                'invoice_date' => Carbon::now()->subDays(7),
                'sent_to' => $contacts->first()->contact_id,
                'invoice_amount' => 1200.00,
                'gst_amount' => 120.00,
                'invoice_total' => 1320.00,
                'invoice_due_date' => Carbon::now()->addDays(23),
                'invoice_status' => 'Due',
                'invoice_payment_status' => 'Unpaid',
                'invoice_paid_amount' => 0.00,
                'invoice_attachment' => 'invoices/inv-005.pdf',
            ],
        ];

        foreach ($invoiceData as $data) {
            Invoice::create($data);
        }

        $this->command->info('Invoices seeded successfully!');
    }
}
