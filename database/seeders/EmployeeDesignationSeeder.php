<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmployeeDesignation;
use App\Models\Employee;
use App\Models\Company;
use Carbon\Carbon;

class EmployeeDesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $employees = $company->employees;

            foreach ($employees as $employee) {
                // Create current designation for each employee
                EmployeeDesignation::create([
                    'company_id' => $company->company_id,
                    'employee_id' => $employee->emp_id,
                    'designation' => $this->getDesignation($company->company_id, $employee->emp_id),
                    'designation_start_date' => $employee->emp_start_date,
                    'designation_end_date' => null, // Current designation
                    'record_status' => 'Active',
                ]);

                // Create historical designations for some employees
                if (in_array($employee->emp_id % 5, [0, 2, 4])) {
                    $this->createHistoricalDesignations($company, $employee);
                }
            }
        }
    }

    private function getDesignation($companyId, $employeeId)
    {
        $designations = [
            1 => [ // TechCorp Solutions
                'Chief Executive Officer',
                'Chief Technology Officer',
                'Senior Software Engineer',
                'Software Engineer',
                'Product Manager',
                'Business Analyst',
                'Quality Assurance Engineer',
                'DevOps Engineer',
            ],
            2 => [ // Global Innovations
                'Managing Director',
                'Head of Innovation',
                'Senior Research Scientist',
                'Research Scientist',
                'Project Manager',
                'Data Analyst',
                'Laboratory Technician',
                'Research Assistant',
            ],
            3 => [ // Digital Dynamics
                'Founder',
                'Technical Director',
                'Senior Developer',
                'Web Developer',
                'UI/UX Designer',
                'Marketing Manager',
                'Content Creator',
                'Customer Support',
            ],
            4 => [ // Future Systems
                'CEO',
                'CTO',
                'Senior Systems Engineer',
                'Systems Engineer',
                'Project Coordinator',
                'Technical Writer',
                'Systems Administrator',
                'Support Engineer',
            ],
            5 => [ // Smart Solutions Group
                'Managing Partner',
                'Solutions Architect',
                'Senior Consultant',
                'Business Consultant',
                'Project Manager',
                'Business Analyst',
                'Junior Consultant',
                'Administrative Assistant',
            ],
        ];

        $companyDesignations = $designations[$companyId] ?? $designations[1];
        $index = ($employeeId - 1) % count($companyDesignations);
        
        return $companyDesignations[$index];
    }

    private function createHistoricalDesignations($company, $employee)
    {
        $historicalDesignations = [
            1 => [ // TechCorp Solutions
                'Junior Software Engineer',
                'Software Engineer',
                'Senior Software Engineer',
            ],
            2 => [ // Global Innovations
                'Research Assistant',
                'Research Scientist',
                'Senior Research Scientist',
            ],
            3 => [ // Digital Dynamics
                'Intern',
                'Junior Developer',
                'Web Developer',
            ],
            4 => [ // Future Systems
                'Junior Systems Engineer',
                'Systems Engineer',
                'Senior Systems Engineer',
            ],
            5 => [ // Smart Solutions Group
                'Junior Consultant',
                'Business Consultant',
                'Senior Consultant',
            ],
        ];

        $companyDesignations = $historicalDesignations[$company->company_id] ?? $historicalDesignations[1];
        
        // Create 1-2 historical designations
        $historicalCount = rand(1, 2);
        
        for ($i = 0; $i < $historicalCount; $i++) {
            $startDate = Carbon::parse($employee->emp_start_date)->addMonths($i * 6);
            $endDate = Carbon::parse($startDate)->addMonths(6);
            
            // Don't create historical designations that overlap with current
            if ($endDate->lt(Carbon::now())) {
                EmployeeDesignation::create([
                    'company_id' => $company->company_id,
                    'employee_id' => $employee->emp_id,
                    'designation' => $companyDesignations[$i] ?? 'Previous Role',
                    'designation_start_date' => $startDate->format('Y-m-d'),
                    'designation_end_date' => $endDate->format('Y-m-d'),
                    'record_status' => 'Inactive',
                ]);
            }
        }
    }
}
