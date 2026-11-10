<?php

require_once 'vendor/autoload.php';

use App\Models\EmploymentInformation;
use App\Models\Employee;

echo "Testing Employment Information System\n";
echo "====================================\n\n";

try {
    // Test 1: Create Employment Information
    echo "1. Testing Employment Information Creation...\n";
    
    // Get an employee for testing
    $employee = Employee::first();
    if (!$employee) {
        echo "   ⚠️  No employees found for testing\n";
        exit;
    }
    
    echo "   Using employee: {$employee->full_name} (ID: {$employee->emp_id})\n";
    
    // Check if employment info already exists
    $existingInfo = EmploymentInformation::where('employee_id', $employee->emp_id)->first();
    if ($existingInfo) {
        echo "   ⚠️  Employment info already exists for this employee\n";
        $employmentInfo = $existingInfo;
    } else {
        // Create new employment info
        $employmentInfo = EmploymentInformation::create([
            'employee_id' => $employee->emp_id,
            'employment_type' => 'Employee',
            'income_type' => 'Salary and wages',
            'employment_basis' => 'Full-time',
            'start_date' => '2024-01-01',
            'classification' => 'Level 1',
            'pay_frequency' => 'Fortnightly Calendar',
            'employee_group' => 'Sales Team',
            'holiday_group' => 'Standard',
            'include_holidays_in_payslips' => true,
            'ordinary_earnings_rate' => 'Ordinary Hours',
            'authorised_to_approve_leave' => true,
            'authorised_to_approve_timesheets' => false,
            'authorised_fields' => 'employee_name,email,phone,address',
            'notes' => 'Test employment information',
            'is_active' => true,
        ]);
        echo "   ✅ Created employment info for {$employee->full_name}\n";
    }
    
    echo "\n";

    // Test 2: Test Authorized Fields
    echo "2. Testing Authorized Fields...\n";
    
    echo "   Current authorized fields: {$employmentInfo->authorized_fields_string}\n";
    echo "   Authorized fields array: " . implode(', ', $employmentInfo->authorized_fields_array) . "\n";
    echo "   Authorized fields count: {$employmentInfo->authorized_fields_count}\n";
    
    // Test adding a field
    echo "   Adding 'salary' field...\n";
    $employmentInfo->addAuthorizedField('salary');
    echo "   ✅ Added 'salary' field. New count: {$employmentInfo->authorized_fields_count}\n";
    
    // Test checking if field exists
    echo "   Checking if 'salary' field exists: " . ($employmentInfo->hasAuthorizedField('salary') ? 'Yes' : 'No') . "\n";
    echo "   Checking if 'nonexistent' field exists: " . ($employmentInfo->hasAuthorizedField('nonexistent') ? 'Yes' : 'No') . "\n";
    
    // Test removing a field
    echo "   Removing 'phone' field...\n";
    $employmentInfo->removeAuthorizedField('phone');
    echo "   ✅ Removed 'phone' field. New count: {$employmentInfo->authorized_fields_count}\n";
    
    // Test setting fields in JSON format
    echo "   Setting fields in JSON format...\n";
    $jsonFields = ['name', 'email', 'department', 'position'];
    $employmentInfo->setAuthorizedFields($jsonFields, 'json');
    echo "   ✅ Set JSON fields. New count: {$employmentInfo->authorized_fields_count}\n";
    echo "   JSON format: " . $employmentInfo->authorized_fields . "\n";
    
    // Test setting fields in comma format
    echo "   Setting fields in comma format...\n";
    $commaFields = ['id', 'status', 'manager', 'location'];
    $employmentInfo->setAuthorizedFields($commaFields, 'comma');
    echo "   ✅ Set comma fields. New count: {$employmentInfo->authorized_fields_count}\n";
    echo "   Comma format: " . $employmentInfo->authorized_fields . "\n";
    
    echo "\n";

    // Test 3: Test Superannuation Management
    echo "3. Testing Superannuation Management...\n";
    
    // Add superannuation fund
    echo "   Adding HESTA Super fund...\n";
    $employmentInfo->addSuperannuationFund('HESTA Super', '1234', 'HESTA');
    echo "   ✅ Added HESTA Super fund\n";
    
    // Add another fund
    echo "   Adding Australian Super fund...\n";
    $employmentInfo->addSuperannuationFund('Australian Super', '5678', 'AUSUP');
    echo "   ✅ Added Australian Super fund\n";
    
    // Check funds
    $funds = $employmentInfo->superannuation_funds;
    echo "   Total superannuation funds: " . count($funds) . "\n";
    foreach ($funds as $fund) {
        echo "     - {$fund['fund_name']} (Member: {$fund['member_number']})\n";
    }
    
    // Test fund operations
    echo "   Checking if HESTA fund exists: " . ($employmentInfo->hasSuperannuationFund('1234') ? 'Yes' : 'No') . "\n";
    echo "   Getting HESTA fund details: " . json_encode($employmentInfo->getSuperannuationFund('1234')) . "\n";
    
    // Update fund
    echo "   Updating HESTA fund...\n";
    $employmentInfo->updateSuperannuationFund('1234', ['fund_name' => 'HESTA Super (Updated)']);
    echo "   ✅ Updated HESTA fund\n";
    
    // Remove fund
    echo "   Removing Australian Super fund...\n";
    $employmentInfo->removeSuperannuationFund('5678');
    echo "   ✅ Removed Australian Super fund. New count: " . count($employmentInfo->superannuation_funds) . "\n";
    
    echo "\n";

    // Test 4: Test Scopes and Queries
    echo "4. Testing Scopes and Queries...\n";
    
    $stats = EmploymentInformation::getEmploymentStatistics();
    echo "   Employment Statistics:\n";
    echo "     - Total employees: {$stats['total_employees']}\n";
    echo "     - Total contractors: {$stats['total_contractors']}\n";
    echo "     - Full-time: {$stats['full_time']}\n";
    echo "     - Part-time: {$stats['part_time']}\n";
    echo "     - With leave approval: {$stats['with_leave_approval']}\n";
    echo "     - With timesheet approval: {$stats['with_timesheet_approval']}\n";
    
    // Test group queries
    $salesTeam = EmploymentInformation::getEmployeesByGroup('Sales Team');
    echo "   Sales Team employees: " . count($salesTeam) . "\n";
    
    $leaveApprovers = EmploymentInformation::getEmployeesWithLeaveApproval();
    echo "   Employees with leave approval: " . count($leaveApprovers) . "\n";
    
    echo "\n";

    // Test 5: Test Accessors
    echo "5. Testing Accessors...\n";
    
    echo "   Years of service: {$employmentInfo->years_of_service}\n";
    echo "   Is employee: " . ($employmentInfo->is_employee ? 'Yes' : 'No') . "\n";
    echo "   Is contractor: " . ($employmentInfo->is_contractor ? 'Yes' : 'No') . "\n";
    echo "   Is full-time: " . ($employmentInfo->is_full_time ? 'Yes' : 'No') . "\n";
    echo "   Has leave approval: " . ($employmentInfo->has_leave_approval ? 'Yes' : 'No') . "\n";
    echo "   Has timesheet approval: " . ($employmentInfo->has_timesheet_approval ? 'Yes' : 'No') . "\n";
    
    echo "\n";

    // Test 6: Test Relationships
    echo "6. Testing Relationships...\n";
    
    $relatedEmployee = $employmentInfo->employee;
    echo "   Related employee: {$relatedEmployee->full_name}\n";
    echo "   Employee company: {$relatedEmployee->company->company_name}\n";
    
    // Test reverse relationship
    $employeeEmploymentInfo = $employee->employmentInformation;
    if ($employeeEmploymentInfo) {
        echo "   Employee has employment info: Yes\n";
        echo "   Employment type: {$employeeEmploymentInfo->employment_type}\n";
        echo "   Employment basis: {$employeeEmploymentInfo->employment_basis}\n";
    } else {
        echo "   Employee has employment info: No\n";
    }
    
    echo "\n";

    echo "✅ All tests completed successfully!\n";
    echo "\nSystem Summary:\n";
    echo "- Employment Information records: " . EmploymentInformation::count() . "\n";
    echo "- Employees with employment info: " . Employee::whereHas('employmentInformation')->count() . "\n";
    echo "- Active employment records: " . EmploymentInformation::active()->count() . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
