<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'plan_name' => 'Free',
                'description' => 'Basic plan for small businesses and startups',
                'price' => 0.00,
                'billing_cycle' => 'monthly',
                'billing_cycle_days' => 30,
                'max_users' => 1,
                'max_companies' => 1,
                'max_invoices_per_month' => 10,
                'max_quotes_per_month' => 10,
                'features' => [
                    'Basic invoicing',
                    'Basic quoting',
                    'Email support',
                    'Standard templates'
                ],
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'plan_name' => 'Starter',
                'description' => 'Perfect for growing businesses',
                'price' => 29.99,
                'billing_cycle' => 'monthly',
                'billing_cycle_days' => 30,
                'max_users' => 3,
                'max_companies' => 2,
                'max_invoices_per_month' => 100,
                'max_quotes_per_month' => 100,
                'features' => [
                    'Advanced invoicing',
                    'Advanced quoting',
                    'Multiple companies',
                    'Priority support',
                    'Custom templates',
                    'Basic reporting'
                ],
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'plan_name' => 'Professional',
                'description' => 'For established businesses with multiple users',
                'price' => 79.99,
                'billing_cycle' => 'monthly',
                'billing_cycle_days' => 30,
                'max_users' => 10,
                'max_companies' => 5,
                'max_invoices_per_month' => 500,
                'max_quotes_per_month' => 500,
                'features' => [
                    'Unlimited invoicing',
                    'Unlimited quoting',
                    'Multiple companies',
                    'Advanced reporting',
                    'API access',
                    'Priority support',
                    'Custom branding',
                    'Advanced analytics'
                ],
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
            ],
            [
                'plan_name' => 'Enterprise',
                'description' => 'For large organizations with unlimited needs',
                'price' => 199.99,
                'billing_cycle' => 'monthly',
                'billing_cycle_days' => 30,
                'max_users' => null, // unlimited
                'max_companies' => null, // unlimited
                'max_invoices_per_month' => null, // unlimited
                'max_quotes_per_month' => null, // unlimited
                'features' => [
                    'Unlimited everything',
                    'White-label solution',
                    'Dedicated support',
                    'Custom integrations',
                    'Advanced security',
                    'SLA guarantee',
                    'On-premise option',
                    'Custom development'
                ],
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::create($planData);
        }

        $this->command->info('Subscription plans created successfully!');
    }
}
