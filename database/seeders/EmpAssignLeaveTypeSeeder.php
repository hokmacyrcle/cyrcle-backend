<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmpAssignLeaveType;
use App\Models\Employee;
use App\Models\Company;
use App\Models\User;

class EmpAssignLeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $users = User::all();

        if ($companies->isEmpty() || $users->isEmpty()) {
            $this->command->warn('No companies or users found. Please run CompanySeeder and UserSeeder first.');
            return;
        }

        $leaveTypes = [
            'Annual Leave',
            'Sick Leave',
            'Personal Leave',
            'Compassionate Leave (paid)',
            'Compassionate Leave (unpaid)',
            'Long Service Leave',
            'Other Unpaid Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Study Leave',
        ];

        $leaveCategories = [
            'Paid Leave',
            'Unpaid Leave',
            'Special Leave',
            'Emergency Leave',
        ];

        $calculationMethods = [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10
        ];

        $terminationOptions = ['Not Paid Out', 'Paid_out'];

        foreach ($companies as $company) {
            $employees = Employee::where('emp_company_id', $company->company_id)->get();
            
            // Get the user associated with this company
            $companyUser = $users->where('id', $company->user_id)->first();
            
            if ($employees->isEmpty() || !$companyUser) {
                continue;
            }

            foreach ($employees as $employee) {
                // Create 2-4 leave type assignments per employee
                $assignmentsCount = rand(2, 4);
                
                for ($i = 0; $i < $assignmentsCount; $i++) {
                    $leaveType = $leaveTypes[array_rand($leaveTypes)];
                    $leaveCategory = $leaveCategories[array_rand($leaveCategories)];
                    $calculationMethodId = $calculationMethods[array_rand($calculationMethods)];
                    $terminationOption = $terminationOptions[array_rand($terminationOptions)];
                    
                    EmpAssignLeaveType::create([
                        'leave_type_id' => rand(1, 100), // Assuming leave types have IDs 1-100
                        'leave_cat' => $leaveCategory,
                        'leave_calculation_method_id' => $calculationMethodId,
                        'hours_accured_annually' => $this->getHoursAccruedAnnually($leaveType),
                        'openning_balance' => $this->getOpeningBalance($leaveType),
                        'hours_employee_works_fortnightly' => $this->getFortnightlyHours($employee),
                        'termination_unused_balnce' => $terminationOption,
                        'employee_id' => $employee->emp_id,
                        'created_by' => $companyUser->id,
                        'updated_by' => rand(0, 1) ? $companyUser->id : null,
                        'company_id' => $company->company_id,
                    ]);
                }
            }
        }

        $this->command->info('EmpAssignLeaveTypeSeeder completed successfully!');
    }

    private function getHoursAccruedAnnually($leaveType)
    {
        $hoursMap = [
            'Annual Leave' => rand(150, 200), // 4-5 weeks
            'Sick Leave' => rand(80, 120),    // 2-3 weeks
            'Personal Leave' => rand(40, 80), // 1-2 weeks
            'Compassionate Leave (paid)' => rand(5, 20), // 1-2.5 days
            'Compassionate Leave (unpaid)' => rand(5, 20),
            'Long Service Leave' => rand(0, 50), // Varies by tenure
            'Other Unpaid Leave' => 0,
            'Maternity Leave' => rand(0, 100), // Varies
            'Paternity Leave' => rand(0, 20),  // 1-2.5 days
            'Study Leave' => rand(0, 40),      // 1 week
        ];

        return $hoursMap[$leaveType] ?? rand(20, 100);
    }

    private function getOpeningBalance($leaveType)
    {
        $balanceMap = [
            'Annual Leave' => rand(0, 100),
            'Sick Leave' => rand(0, 50),
            'Personal Leave' => rand(0, 40),
            'Compassionate Leave (paid)' => 0,
            'Compassionate Leave (unpaid)' => 0,
            'Long Service Leave' => rand(0, 200),
            'Other Unpaid Leave' => 0,
            'Maternity Leave' => rand(0, 80),
            'Paternity Leave' => 0,
            'Study Leave' => rand(0, 20),
        ];

        return $balanceMap[$leaveType] ?? rand(0, 50);
    }

    private function getFortnightlyHours($employee)
    {
        // Based on employment type
        $employmentType = $employee->emp_employment_type;
        
        switch ($employmentType) {
            case 'Full Time':
                return rand(70, 80); // 35-40 hours per week
            case 'Part Time':
                return rand(30, 60); // 15-30 hours per week
            case 'Casual':
                return rand(20, 50); // 10-25 hours per week
            case 'Contract':
                return rand(60, 80); // 30-40 hours per week
            default:
                return rand(40, 80);
        }
    }
}
