<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceItem;
use App\Models\Invoice;

class InvoiceItemSeeder extends Seeder
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

        $itemData = [
            // Invoice 1 - Web Development Services
            [
                'invoice_id' => $invoices[0]->invoice_id,
                'item_description' => 'Website Design & Development',
                'item_quantity' => 1,
                'item_rate' => 1200.00,
                'item_cost' => 1200.00,
                'item_gst' => 120.00,
                'item_total' => 1320.00,
            ],
            [
                'invoice_id' => $invoices[0]->invoice_id,
                'item_description' => 'SEO Optimization',
                'item_quantity' => 1,
                'item_rate' => 300.00,
                'item_cost' => 300.00,
                'item_gst' => 30.00,
                'item_total' => 330.00,
            ],

            // Invoice 2 - Marketing Services
            [
                'invoice_id' => $invoices[1]->invoice_id,
                'item_description' => 'Digital Marketing Campaign',
                'item_quantity' => 1,
                'item_rate' => 1500.00,
                'item_cost' => 1500.00,
                'item_gst' => 150.00,
                'item_total' => 1650.00,
            ],
            [
                'invoice_id' => $invoices[1]->invoice_id,
                'item_description' => 'Social Media Management',
                'item_quantity' => 1,
                'item_rate' => 800.00,
                'item_cost' => 800.00,
                'item_gst' => 80.00,
                'item_total' => 880.00,
            ],
            [
                'invoice_id' => $invoices[1]->invoice_id,
                'item_description' => 'Content Creation',
                'item_quantity' => 1,
                'item_rate' => 200.00,
                'item_cost' => 200.00,
                'item_gst' => 20.00,
                'item_total' => 220.00,
            ],

            // Invoice 3 - Consulting Services
            [
                'invoice_id' => $invoices[2]->invoice_id,
                'item_description' => 'Business Strategy Consultation',
                'item_quantity' => 4,
                'item_rate' => 200.00,
                'item_cost' => 800.00,
                'item_gst' => 80.00,
                'item_total' => 880.00,
            ],

            // Invoice 4 - IT Services
            [
                'invoice_id' => $invoices[3]->invoice_id,
                'item_description' => 'Server Maintenance',
                'item_quantity' => 1,
                'item_rate' => 800.00,
                'item_cost' => 800.00,
                'item_gst' => 80.00,
                'item_total' => 880.00,
            ],
            [
                'invoice_id' => $invoices[3]->invoice_id,
                'item_description' => 'Database Optimization',
                'item_quantity' => 1,
                'item_rate' => 600.00,
                'item_cost' => 600.00,
                'item_gst' => 60.00,
                'item_total' => 660.00,
            ],
            [
                'invoice_id' => $invoices[3]->invoice_id,
                'item_description' => 'Security Audit',
                'item_quantity' => 1,
                'item_rate' => 900.00,
                'item_cost' => 900.00,
                'item_gst' => 90.00,
                'item_total' => 990.00,
            ],
            [
                'invoice_id' => $invoices[3]->invoice_id,
                'item_description' => 'Backup System Setup',
                'item_quantity' => 1,
                'item_rate' => 900.00,
                'item_cost' => 900.00,
                'item_gst' => 90.00,
                'item_total' => 990.00,
            ],

            // Invoice 5 - Design Services
            [
                'invoice_id' => $invoices[4]->invoice_id,
                'item_description' => 'Logo Design',
                'item_quantity' => 1,
                'item_rate' => 500.00,
                'item_cost' => 500.00,
                'item_gst' => 50.00,
                'item_total' => 550.00,
            ],
            [
                'invoice_id' => $invoices[4]->invoice_id,
                'item_description' => 'Brand Identity Package',
                'item_quantity' => 1,
                'item_rate' => 700.00,
                'item_cost' => 700.00,
                'item_gst' => 70.00,
                'item_total' => 770.00,
            ],
        ];

        foreach ($itemData as $data) {
            InvoiceItem::create($data);
        }

        $this->command->info('Invoice items seeded successfully!');
    }
}
