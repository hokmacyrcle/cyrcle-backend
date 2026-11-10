<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [
            [
                'company_name' => 'Tech Solutions Inc.',
                'phone' => '+1-555-0123',
                'website' => 'https://techsolutions.com',
                'vat_number' => 'VAT123456789',
                'gst_number' => 'GST987654321',
                'address' => '123 Tech Street, Silicon Valley, CA 94025',
                'email' => 'contact@techsolutions.com',
                'status' => 'active'
            ],
            [
                'company_name' => 'Global Manufacturing Ltd.',
                'phone' => '+44-20-7946-0958',
                'website' => 'https://globalmanufacturing.co.uk',
                'vat_number' => 'GB123456789',
                'gst_number' => 'GST456789123',
                'address' => '456 Industrial Ave, London, UK SW1A 1AA',
                'email' => 'info@globalmanufacturing.co.uk',
                'status' => 'active'
            ],
            [
                'company_name' => 'Digital Innovations Corp.',
                'phone' => '+61-2-9876-5432',
                'website' => 'https://digitalinnovations.com.au',
                'vat_number' => 'AU987654321',
                'gst_number' => 'GST321654987',
                'address' => '789 Innovation Drive, Sydney, NSW 2000',
                'email' => 'hello@digitalinnovations.com.au',
                'status' => 'active'
            ],
            [
                'company_name' => 'Green Energy Solutions',
                'phone' => '+49-30-1234-5678',
                'website' => 'https://greenenergy.de',
                'vat_number' => 'DE567891234',
                'gst_number' => 'GST789123456',
                'address' => '321 Green Street, Berlin, Germany 10115',
                'email' => 'info@greenenergy.de',
                'status' => 'inactive'
            ],
            [
                'company_name' => 'Healthcare Systems Ltd.',
                'phone' => '+91-11-2345-6789',
                'website' => 'https://healthcaresystems.in',
                'vat_number' => 'IN345678901',
                'gst_number' => 'GST234567890',
                'address' => '654 Health Avenue, New Delhi, India 110001',
                'email' => 'contact@healthcaresystems.in',
                'status' => 'active'
            ]
        ];

        foreach ($clients as $client) {
            Client::create($client);
        }
    }
}
