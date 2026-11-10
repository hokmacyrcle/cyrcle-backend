<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
                // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            SubscriptionPlanSeeder::class,
            UserSeeder::class,
            ClientSeeder::class,
            CompanySeeder::class,
            LocationSeeder::class,
            ContactSeeder::class,
            EmployeeSeeder::class,
            EmployeeDesignationSeeder::class,
            TaxRateSeeder::class,
            PaymentTermSeeder::class,
            QuoteSeeder::class,
            QuoteVersionSeeder::class,
            InvoiceSeeder::class,
            InvoiceItemSeeder::class,
            InvoiceTransactionSeeder::class,
            AttachmentSeeder::class,
            DropdownSeeder::class,
            EmpAssignLeaveTypeSeeder::class,
        ]);
    }
}
