<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Company;
use App\Models\Contact;
use App\Models\PaymentTerm;

class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing companies, contacts, and payment terms
        $companies = Company::all();
        $contacts = Contact::all();
        $paymentTerms = PaymentTerm::all();

        if ($companies->isEmpty() || $contacts->isEmpty()) {
            $this->command->warn('Skipping QuoteSeeder: No companies or contacts found. Please run CompanySeeder and ContactSeeder first.');
            return;
        }

        $this->command->info("Found {$companies->count()} companies, {$contacts->count()} contacts, {$paymentTerms->count()} payment terms");

        $statuses = ['Pending', 'Accepted', 'Closed', 'Rejected'];
        $products = [
            'Web Development Services',
            'Mobile App Development',
            'Consulting Services',
            'Design Services',
            'SEO Services',
            'Maintenance & Support',
            'Training Services',
            'Custom Software Development'
        ];

        for ($i = 1; $i <= 20; $i++) {
            // Use array_rand instead of collection random() method
            $companyIndex = array_rand($companies->toArray());
            $contactIndex = array_rand($contacts->toArray());
            $paymentTermIndex = array_rand($paymentTerms->toArray());
            
            $company = $companies[$companyIndex];
            $contact = $contacts[$contactIndex];
            $paymentTerm = $paymentTerms[$paymentTermIndex];
            $status = $statuses[array_rand($statuses)];
            
            // Debug output
            $this->command->info("Quote $i - Company ID: " . ($company ? $company->company_id : 'NULL') . 
                                ", Contact ID: " . ($contact ? $contact->contact_id : 'NULL') . 
                                ", Payment Term ID: " . ($paymentTerm ? $paymentTerm->id : 'NULL'));
            
            // Ensure we have valid IDs
            if (!$company || !$contact || !$paymentTerm) {
                $this->command->warn("Skipping quote $i: Missing required data");
                continue;
            }
            
            $quote = Quote::create([
                'company_id' => $company->company_id,
                'contact_id' => $contact->contact_id,
                'payment_term_id' => $paymentTerm->id,
                'quote_number' => (1000 + $i),
                'quote_date' => now()->subDays(rand(1, 365)),
                'expiration_date' => now()->addDays(rand(30, 90)),
                'status' => $status,
                'customer_email' => $contact->contact_email_address,
                'billing_address' => $contact->contact_address_line_1 ?? '123 Business Street, City, State 12345',
                'subtotal' => 0,
                'discount_percent' => rand(0, 15),
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'message_on_quote' => 'Thank you for your interest in our services. We look forward to working with you.',
                'message_on_statement' => 'Payment is due according to the terms specified above.',
                'notes' => 'This quote is valid for 30 days from the date of issue.',
                'terms' => 'Payment terms: Net 30 days. Late payments may incur additional charges.',
                'attachments' => null,
                'send_later' => false,
                'scheduled_at' => null,
            ]);

            // Create quote items
            $numItems = rand(1, 5);
            $subtotal = 0;
            
            for ($j = 1; $j <= $numItems; $j++) {
                $quantity = rand(1, 10);
                $rate = rand(50, 500);
                $amount = $quantity * $rate;
                $gst = $amount * 0.10; // 10% GST
                $subtotal += $amount;

                QuoteItem::create([
                    'quote_id' => $quote->quote_id,
                    'product_service' => $products[array_rand($products)],
                    'description' => 'Professional ' . strtolower($products[array_rand($products)]) . ' with high quality standards.',
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'amount' => $amount,
                    'gst' => $gst,
                    'class' => ['Services', 'Products', 'Consulting'][array_rand(['Services', 'Products', 'Consulting'])],
                ]);
            }

            // Calculate totals
            $discountAmount = ($subtotal * $quote->discount_percent) / 100;
            $taxAmount = ($subtotal - $discountAmount) * 0.10; // 10% GST on discounted amount
            $total = $subtotal - $discountAmount + $taxAmount;

            $quote->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);
        }

        $this->command->info('QuoteSeeder completed successfully. Created 20 sample quotes.');
    }
}
