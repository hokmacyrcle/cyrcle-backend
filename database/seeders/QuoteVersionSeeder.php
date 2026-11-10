<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\QuoteVersionItem;
use App\Models\User;

class QuoteVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get a user for creating versions
        $user = User::first();
        if (!$user) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        // Get existing quotes
        $quotes = Quote::with('items')->take(5)->get();
        
        if ($quotes->isEmpty()) {
            $this->command->error('No quotes found. Please run QuoteSeeder first.');
            return;
        }

        foreach ($quotes as $quote) {
            $this->createVersionsForQuote($quote, $user);
        }

        $this->command->info('Quote versions seeded successfully!');
    }

    private function createVersionsForQuote($quote, $user)
    {
        // Create initial version (1.0)
        $version1 = QuoteVersion::create([
            'quote_id' => $quote->quote_id,
            'version_number' => '1.0',
            'created_by' => $user->id,
            'company_id' => $quote->company_id,
            'contact_id' => $quote->contact_id,
            'payment_term_id' => $quote->payment_term_id,
            'quote_number' => $quote->quote_number,
            'quote_date' => $quote->quote_date,
            'expiration_date' => $quote->expiration_date,
            'status' => $quote->status,
            'subtotal' => $quote->subtotal,
            'discount_percent' => $quote->discount_percent,
            'discount_amount' => $quote->discount_amount,
            'tax_amount' => $quote->tax_amount,
            'total' => $quote->total,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'attachments' => $quote->attachments,
            'change_summary' => 'Initial quote creation',
            'change_reason' => 'Quote created with initial data',
            'is_current_version' => false,
        ]);

        // Create version items for version 1.0
        foreach ($quote->items as $item) {
            QuoteVersionItem::create([
                'quote_version_id' => $version1->id,
                'product_service' => $item->product_service,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'rate' => $item->rate,
                'amount' => $item->amount,
                'gst' => $item->gst,
                'gst_rate' => $item->gst_rate ?? 10.00,
                'total' => $item->amount + $item->gst,
            ]);
        }

        // Create updated version (1.1) - with some changes
        $version2 = QuoteVersion::create([
            'quote_id' => $quote->quote_id,
            'version_number' => '1.1',
            'created_by' => $user->id,
            'company_id' => $quote->company_id,
            'contact_id' => $quote->contact_id,
            'payment_term_id' => $quote->payment_term_id,
            'quote_number' => $quote->quote_number,
            'quote_date' => $quote->quote_date,
            'expiration_date' => $quote->expiration_date,
            'status' => $quote->status,
            'subtotal' => $quote->subtotal * 1.05, // 5% increase
            'discount_percent' => $quote->discount_percent,
            'discount_amount' => $quote->discount_amount * 1.05,
            'tax_amount' => $quote->tax_amount * 1.05,
            'total' => $quote->total * 1.05,
            'notes' => $quote->notes . ' (Updated with price adjustments)',
            'terms' => $quote->terms,
            'attachments' => $quote->attachments,
            'change_summary' => 'Price adjustments, Notes updated',
            'change_reason' => 'Customer requested price adjustments',
            'is_current_version' => false,
        ]);

        // Create version items for version 1.1 (with updated prices)
        foreach ($quote->items as $item) {
            QuoteVersionItem::create([
                'quote_version_id' => $version2->id,
                'product_service' => $item->product_service,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'rate' => $item->rate * 1.05, // 5% increase
                'amount' => $item->amount * 1.05,
                'gst' => $item->gst * 1.05,
                'gst_rate' => $item->gst_rate ?? 10.00,
                'total' => ($item->amount + $item->gst) * 1.05,
            ]);
        }

        // Create latest version (2.0) - major changes
        $version3 = QuoteVersion::create([
            'quote_id' => $quote->quote_id,
            'version_number' => '2.0',
            'created_by' => $user->id,
            'company_id' => $quote->company_id,
            'contact_id' => $quote->contact_id,
            'payment_term_id' => $quote->payment_term_id,
            'quote_number' => $quote->quote_number,
            'quote_date' => $quote->quote_date,
            'expiration_date' => $quote->expiration_date,
            'status' => $quote->status,
            'subtotal' => $quote->subtotal * 1.10, // 10% increase from original
            'discount_percent' => $quote->discount_percent,
            'discount_amount' => $quote->discount_amount * 1.10,
            'tax_amount' => $quote->tax_amount * 1.10,
            'total' => $quote->total * 1.10,
            'notes' => $quote->notes . ' (Major revision with new pricing structure)',
            'terms' => $quote->terms . ' (Updated terms and conditions)',
            'attachments' => $quote->attachments,
            'change_summary' => 'Major revision, New pricing structure, Terms updated',
            'change_reason' => 'Complete quote revision based on new requirements',
            'is_current_version' => true, // This is the current version
        ]);

        // Create version items for version 2.0 (with major changes)
        foreach ($quote->items as $item) {
            QuoteVersionItem::create([
                'quote_version_id' => $version3->id,
                'product_service' => $item->product_service,
                'description' => $item->description . ' (Enhanced)',
                'quantity' => $item->quantity,
                'rate' => $item->rate * 1.10, // 10% increase from original
                'amount' => $item->amount * 1.10,
                'gst' => $item->gst * 1.10,
                'gst_rate' => $item->gst_rate ?? 10.00,
                'total' => ($item->amount + $item->gst) * 1.10,
                'notes' => 'Enhanced version with improved features',
            ]);
        }

        $this->command->info("Created 3 versions for quote {$quote->quote_number}");
    }
}
