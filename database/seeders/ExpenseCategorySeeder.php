<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Office Supplies', 'code' => 'OFF-SUP', 'description' => 'General office supplies and stationery'],
            ['name' => 'Travel & Transportation', 'code' => 'TRAVEL', 'description' => 'Business travel and transportation expenses'],
            ['name' => 'Meals & Entertainment', 'code' => 'MEALS', 'description' => 'Business meals and entertainment'],
            ['name' => 'Professional Services', 'code' => 'PRO-SVC', 'description' => 'Legal, accounting, and consulting services'],
            ['name' => 'Marketing & Advertising', 'code' => 'MKT-ADV', 'description' => 'Marketing and advertising expenses'],
            ['name' => 'Utilities', 'code' => 'UTIL', 'description' => 'Electricity, water, internet, phone bills'],
            ['name' => 'Rent & Lease', 'code' => 'RENT', 'description' => 'Office rent and equipment lease payments'],
            ['name' => 'Equipment & Software', 'code' => 'EQUIP', 'description' => 'Computer equipment and software purchases'],
            ['name' => 'Training & Education', 'code' => 'TRAIN', 'description' => 'Employee training and education'],
            ['name' => 'Insurance', 'code' => 'INS', 'description' => 'Business insurance premiums'],
            ['name' => 'Maintenance & Repairs', 'code' => 'MAINT', 'description' => 'Equipment and facility maintenance'],
            ['name' => 'Other Expenses', 'code' => 'OTHER', 'description' => 'Miscellaneous business expenses'],
        ];

        // Get all companies to create categories for each
        $companies = \DB::table('companies')->pluck('company_id');

        foreach ($companies as $companyId) {
            foreach ($categories as $category) {
                \DB::table('expense_categories')->insert(array_merge($category, [
                    'company_id' => $companyId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}
