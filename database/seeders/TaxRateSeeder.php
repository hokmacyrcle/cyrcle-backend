<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TaxRate;

class TaxRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxRates = [
            [
                'tax_name' => 'GST',
                'agency_name' => 'Australian Taxation Office',
                'rate' => 10.00,
                'type' => 'single',
                'is_active' => true,
                'description' => 'Goods and Services Tax',
            ],
            [
                'tax_name' => 'VAT',
                'agency_name' => 'HM Revenue & Customs',
                'rate' => 20.00,
                'type' => 'single',
                'is_active' => true,
                'description' => 'Value Added Tax',
            ],
            [
                'tax_name' => 'Sales Tax',
                'agency_name' => 'State Revenue Office',
                'rate' => 8.50,
                'type' => 'single',
                'is_active' => true,
                'description' => 'State Sales Tax',
            ],
            [
                'tax_name' => 'Combined Tax',
                'agency_name' => 'Federal Tax Authority',
                'rate' => 15.00,
                'type' => 'combined',
                'is_active' => true,
                'description' => 'Combined Federal and State Tax',
            ],
        ];

        foreach ($taxRates as $taxRate) {
            TaxRate::create($taxRate);
        }
    }
}
