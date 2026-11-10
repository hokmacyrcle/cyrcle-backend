<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Dropdown;

class DropdownSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        Dropdown::truncate();

        // Leave Types
        $leaveTypes = [
            'Annual Leave' => [
                'Standard Annual Leave',
                'Accrued Annual Leave',
                'Pro-rata Annual Leave',
            ],
            'Sick Leave' => [
                'Personal Sick Leave',
                'Carer\'s Leave',
                'Medical Leave',
            ],
            'Personal Leave' => [
                'Personal Emergency Leave',
                'Bereavement Leave',
                'Family Leave',
            ],
            'Compassionate Leave' => [
                'Compassionate Leave (paid)',
                'Compassionate Leave (unpaid)',
            ],
            'Long Service Leave' => [
                'Long Service Leave (7 years)',
                'Long Service Leave (10 years)',
                'Long Service Leave (15 years)',
            ],
            'Maternity Leave' => [
                'Maternity Leave (paid)',
                'Maternity Leave (unpaid)',
                'Pre-natal Leave',
                'Post-natal Leave',
            ],
            'Paternity Leave' => [
                'Paternity Leave (paid)',
                'Paternity Leave (unpaid)',
            ],
            'Study Leave' => [
                'Study Leave (paid)',
                'Study Leave (unpaid)',
                'Exam Leave',
            ],
            'Other Leave' => [
                'Other Unpaid Leave',
                'Jury Duty Leave',
                'Community Service Leave',
                'Military Leave',
            ],
        ];

        // Leave Calculation Methods
        $calculationMethods = [
            'Fixed Amount Each Period',
            'Manually Recorded Rate',
            'No Calculation Required',
            'Based on Ordinary Earnings',
            'Based on Base Salary',
            'Based on Total Earnings',
            'Pro-rata Calculation',
            'Custom Formula',
        ];

        // Termination Options
        $terminationOptions = [
            'Not Paid Out',
            'Paid_out',
        ];

        // Insert Leave Types
        foreach ($leaveTypes as $leaveType => $subTypes) {
            // Create parent leave type
            $parent = Dropdown::create([
                'type' => 'leave_type',
                'name' => $leaveType,
                'parent_id' => null,
            ]);

            // Create sub-types
            foreach ($subTypes as $subType) {
                Dropdown::create([
                    'type' => 'leave_type',
                    'name' => $subType,
                    'parent_id' => $parent->id,
                ]);
            }
        }

        // Insert Leave Calculation Methods
        foreach ($calculationMethods as $method) {
            Dropdown::create([
                'type' => 'leave_calculation_method',
                'name' => $method,
                'parent_id' => null,
            ]);
        }

        // Insert Termination Options
        foreach ($terminationOptions as $option) {
            Dropdown::create([
                'type' => 'termination_option',
                'name' => $option,
                'parent_id' => null,
            ]);
        }

        $this->command->info('DropdownSeeder completed successfully!');
        $this->command->info('Created ' . Dropdown::count() . ' dropdown items.');
    }
}
