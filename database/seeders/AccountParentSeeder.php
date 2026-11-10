<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountParentSeeder extends Seeder
{
    public function run(): void
    {
        // Map of account type => parent accounts to suggest
        $map = [
            'Fixed assets' => [
                'Accumulated depreciation on property, plant and equipment',
                'Furniture and Equipment - AUD',
                'Property, plant and equipment - AUD',
            ],
            'Cash and cash equivalents' => [
                'Operating bank account',
                'Petty cash',
                'Savings account',
            ],
            'Expenses' => [
                'General expenses',
                'Administrative expenses',
            ],
        ];

        $companyIds = DB::table('companies')->pluck('company_id');

        foreach ($companyIds as $companyId) {
            foreach ($map as $accountType => $parents) {
                // Ensure the account type parent exists
                $typeId = DB::table('account_dropdowns')
                    ->where('company_id', $companyId)
                    ->where('type', 'account_type')
                    ->where('name', $accountType)
                    ->value('id');

                if (!$typeId) {
                    $typeId = DB::table('account_dropdowns')->insertGetId([
                        'type' => 'account_type',
                        'name' => $accountType,
                        'parent_id' => null,
                        'company_id' => $companyId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($parents as $name) {
                    $exists = DB::table('account_dropdowns')
                        ->where('company_id', $companyId)
                        ->where('type', 'parent_account')
                        ->where('name', $name)
                        ->where('parent_id', $typeId)
                        ->exists();

                    if (!$exists) {
                        DB::table('account_dropdowns')->insert([
                            'type' => 'parent_account',
                            'name' => $name,
                            'parent_id' => $typeId,
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


