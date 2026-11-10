<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountDetailTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Map of account type => detail types
        $map = [
            'Cash and cash equivalents' => [
                'Cash and cash equivalents',
                'Cash on hand',
                'Client trust account',
                'Money Market',
                'Rents Held in Trust',
                'Savings',
                'Transaction',
            ],
            'Fixed assets' => [
                'Accumulated depletion',
                'Accumulated depreciation on property, plant and equipment',
                'Buildings',
                'Depletable Assets',
                'Furniture and Fixtures',
                'Intangible Assets',
                'Land',
                'Leasehold Improvements',
                'Machinery and Equipment',
                'Vehicles',
            ],
            'Expenses' => [
                'Advertising and marketing',
                'Bank charges',
                'Computer and internet expenses',
                'Dues and subscriptions',
                'Insurance',
                'Office expenses',
                'Rent or lease payments',
                'Repairs and maintenance',
                'Travel',
                'Utilities',
            ],
            'Income' => [
                'Sales of Product Income',
                'Service/Fee Income',
                'Uncategorized Income',
                'Other Primary Income',
            ],
            'Accounts payable (A/P)' => [
                'Trade and other payables',
                'Unapplied cash bill payment expense',
            ],
            'Accounts receivable (A/R)' => [
                'Trade and other receivables',
                'Unbilled receivable',
            ],
        ];

        $companyIds = DB::table('companies')->pluck('company_id');

        foreach ($companyIds as $companyId) {
            foreach ($map as $accountType => $detailTypes) {
                // Ensure parent account_type row exists
                $parentId = DB::table('account_dropdowns')
                    ->where('company_id', $companyId)
                    ->where('type', 'account_type')
                    ->where('name', $accountType)
                    ->value('id');

                if (!$parentId) {
                    $parentId = DB::table('account_dropdowns')->insertGetId([
                        'type' => 'account_type',
                        'name' => $accountType,
                        'parent_id' => null,
                        'company_id' => $companyId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($detailTypes as $name) {
                    $exists = DB::table('account_dropdowns')
                        ->where('company_id', $companyId)
                        ->where('type', 'detail_type')
                        ->where('name', $name)
                        ->where('parent_id', $parentId)
                        ->exists();

                    if (!$exists) {
                        DB::table('account_dropdowns')->insert([
                            'type' => 'detail_type',
                            'name' => $name,
                            'parent_id' => $parentId,
                            'company_id' => $companyId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}


