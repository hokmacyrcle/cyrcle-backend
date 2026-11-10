<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceTransaction;
use App\Models\Invoice;

class InvoiceTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing invoices
        $invoices = Invoice::all();

        if ($invoices->isEmpty()) {
            $this->command->warn('No invoices found. Please run InvoiceSeeder first.');
            return;
        }

        $transactionData = [
            // Invoice 3 - Fully Paid (880.00)
            [
                'invoice_id' => $invoices[2]->invoice_id,
                'payment_id' => 1001,
                'payment_amount' => 880.00,
            ],

            // Invoice 4 - Partially Paid (1760.00 out of 3520.00)
            [
                'invoice_id' => $invoices[3]->invoice_id,
                'payment_id' => 1002,
                'payment_amount' => 1000.00,
            ],
            [
                'invoice_id' => $invoices[3]->invoice_id,
                'payment_id' => 1003,
                'payment_amount' => 760.00,
            ],

            // Additional partial payment for Invoice 4
            [
                'invoice_id' => $invoices[3]->invoice_id,
                'payment_id' => 1004,
                'payment_amount' => 500.00,
            ],
        ];

        foreach ($transactionData as $data) {
            InvoiceTransaction::create($data);
        }

        $this->command->info('Invoice transactions seeded successfully!');
    }
}
