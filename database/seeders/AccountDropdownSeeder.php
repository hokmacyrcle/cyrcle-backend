<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountDropdownSeeder extends Seeder
{
    public function run(): void
    {
        $accountTypes = [
            'Expenses',
            'Cash and cash equivalents',
            'Accounts receivable (A/R)',
            'Current assets',
            'Fixed assets',
            'Non-current assets',
            'Accounts payable (A/P)',
            'Credit cards',
            'Current liabilities',
            'Non-current liabilities',
            'Equity',
            'Income',
            'Other income',
            'Cost of goods sold (COGS)',
            'Other expenses',
        ];

        // Seed for all existing companies
        $companyIds = DB::table('companies')->pluck('company_id');

        foreach ($companyIds as $companyId) {
            foreach ($accountTypes as $name) {
                // Respect unique constraint (company_id, type, name, parent_id)
                $exists = DB::table('account_dropdowns')
                    ->where('company_id', $companyId)
                    ->where('type', 'account_type')
                    ->where('name', $name)
                    ->whereNull('parent_id')
                    ->exists();

                if (!$exists) {
                    DB::table('account_dropdowns')->insert([
                        'type' => 'account_type',
                        'name' => $name,
                        'parent_id' => null,
                        'company_id' => $companyId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}


