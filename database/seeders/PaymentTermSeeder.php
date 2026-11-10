<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaymentTerm;

class PaymentTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentTerms = [
            [
                'name' => 'Net 30',
                'due_calculation_method' => 'fixed_days',
                'fixed_days_value' => 30,
                'certain_day_value' => null,
                'next_month_threshold_days' => null,
                'is_active' => true,
                'description' => 'Payment due in 30 days',
            ],
            [
                'name' => 'Net 15',
                'due_calculation_method' => 'fixed_days',
                'fixed_days_value' => 15,
                'certain_day_value' => null,
                'next_month_threshold_days' => null,
                'is_active' => true,
                'description' => 'Payment due in 15 days',
            ],
            [
                'name' => 'Net 60',
                'due_calculation_method' => 'fixed_days',
                'fixed_days_value' => 60,
                'certain_day_value' => null,
                'next_month_threshold_days' => null,
                'is_active' => true,
                'description' => 'Payment due in 60 days',
            ],
            [
                'name' => 'Due on 15th',
                'due_calculation_method' => 'certain_day',
                'fixed_days_value' => null,
                'certain_day_value' => 15,
                'next_month_threshold_days' => 7,
                'is_active' => true,
                'description' => 'Payment due on 15th of month',
            ],
            [
                'name' => 'Due on 1st',
                'due_calculation_method' => 'certain_day',
                'fixed_days_value' => null,
                'certain_day_value' => 1,
                'next_month_threshold_days' => 5,
                'is_active' => true,
                'description' => 'Payment due on 1st of month',
            ],
        ];

        foreach ($paymentTerms as $paymentTerm) {
            PaymentTerm::create($paymentTerm);
        }
    }
}
