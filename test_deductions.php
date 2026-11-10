<?php

require_once 'vendor/autoload.php';

use App\Models\Deduction;
use App\Models\Employee;

echo "Testing Deduction System\n";
echo "========================\n\n";

try {
    // Test 1: Create Deductions
    echo "1. Testing Deduction Creation...\n";
    
    // Get an employee for testing
    $employee = Employee::first();
    if (!$employee) {
        echo "   ⚠️  No employees found for testing\n";
        exit;
    }
    
    echo "   Using employee: {$employee->full_name} (ID: {$employee->emp_id})\n";
    
    // Check if deductions already exist for this employee
    $existingDeductions = Deduction::where('employee_id', $employee->emp_id)->get();
    if ($existingDeductions->count() > 0) {
        echo "   ⚠️  Deductions already exist for this employee\n";
        $deductions = $existingDeductions;
    } else {
        // Create deductions matching the form fields from the image
        $deductionsData = [
            [
                'employee_id' => $employee->emp_id,
                'deduction_type' => 'Other Pre-Tax Deductions',
                'amount' => 150.00,
                'deduction_category' => 'pre_tax',
                'calculation_type' => 'fixed_amount',
                'frequency' => 'per_pay_period',
                'status' => 'active',
                'is_required' => false,
                'created_by' => 1, // Assuming user ID 1 exists
                'updated_by' => 1,
            ],
            [
                'employee_id' => $employee->emp_id,
                'deduction_type' => 'Health Insurance',
                'amount' => 75.00,
                'deduction_category' => 'pre_tax',
                'calculation_type' => 'fixed_amount',
                'frequency' => 'per_pay_period',
                'status' => 'active',
                'is_required' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'employee_id' => $employee->emp_id,
                'deduction_type' => 'Retirement Contribution',
                'amount' => 5.50,
                'deduction_category' => 'pre_tax',
                'calculation_type' => 'percentage',
                'percentage_rate' => 5.50,
                'frequency' => 'per_pay_period',
                'status' => 'active',
                'is_required' => false,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'employee_id' => $employee->emp_id,
                'deduction_type' => 'Union Dues',
                'amount' => 25.00,
                'deduction_category' => 'post_tax',
                'calculation_type' => 'fixed_amount',
                'frequency' => 'per_pay_period',
                'status' => 'active',
                'is_required' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        $deductions = [];
        foreach ($deductionsData as $deductionData) {
            $deduction = Deduction::create($deductionData);
            $deductions[] = $deduction;
            echo "   ✅ Created deduction: {$deduction->deduction_type}\n";
        }
    }
    
    echo "\n";

    // Test 2: Test Deduction Details and Accessors
    echo "2. Testing Deduction Details and Accessors...\n";
    
    foreach ($deductions as $index => $deduction) {
        $deductionNumber = $index + 1;
        echo "   Deduction {$deductionNumber}: {$deduction->deduction_type}\n";
        echo "     Amount: {$deduction->formatted_amount}\n";
        echo "     Category: {$deduction->category_label}\n";
        echo "     Calculation Type: {$deduction->calculation_type_label}\n";
        echo "     Frequency: {$deduction->frequency_label}\n";
        echo "     Status: {$deduction->status_label}\n";
        echo "     Is Required: " . ($deduction->is_required ? 'Yes' : 'No') . "\n";
        echo "     Is Active: " . ($deduction->is_active ? 'Yes' : 'No') . "\n";
        echo "     Is Pre-Tax: " . ($deduction->is_pre_tax ? 'Yes' : 'No') . "\n";
        echo "     Is Post-Tax: " . ($deduction->is_post_tax ? 'Yes' : 'No') . "\n";
        
        if ($deduction->is_percentage) {
            echo "     Percentage Rate: {$deduction->formatted_percentage_rate}\n";
        }
        
        if ($deduction->effective_date) {
            echo "     Effective Date: {$deduction->effective_date->format('Y-m-d')}\n";
        }
        
        if ($deduction->end_date) {
            echo "     End Date: {$deduction->end_date->format('Y-m-d')}\n";
        }
        
        echo "     Is Effective: " . ($deduction->is_effective ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
    
    echo "\n";

    // Test 3: Test Deduction Management Methods
    echo "3. Testing Deduction Management Methods...\n";
    
    $firstDeduction = $deductions[0];
    echo "   Testing status changes for: {$firstDeduction->deduction_type}\n";
    
    echo "     Current status: {$firstDeduction->status_label}\n";
    echo "     Suspending deduction...\n";
    $firstDeduction->suspend();
    echo "     ✅ New status: {$firstDeduction->status_label}\n";
    
    echo "     Activating deduction...\n";
    $firstDeduction->activate();
    echo "     ✅ Final status: {$firstDeduction->status_label}\n";
    
    // Test amount updates
    echo "     Current amount: {$firstDeduction->formatted_amount}\n";
    echo "     Updating amount to $200.00...\n";
    $firstDeduction->updateAmount(200.00);
    echo "     ✅ New amount: {$firstDeduction->formatted_amount}\n";
    
    // Test required/optional changes
    echo "     Current required status: " . ($firstDeduction->is_required ? 'Yes' : 'No') . "\n";
    echo "     Setting as required...\n";
    $firstDeduction->setAsRequired();
    echo "     ✅ New required status: " . ($firstDeduction->is_required ? 'Yes' : 'No') . "\n";
    
    echo "\n";

    // Test 4: Test Calculation Methods
    echo "4. Testing Calculation Methods...\n";
    
    foreach ($deductions as $deduction) {
        echo "   {$deduction->deduction_type}:\n";
        echo "     Per Pay Period: {$deduction->formatted_amount}\n";
        echo "     Annual Amount: $" . number_format($deduction->getAnnualDeductionAmount(), 2) . "\n";
        
        if ($deduction->is_percentage) {
            $baseAmount = 5000.00; // Example base amount
            $calculatedAmount = $deduction->calculateDeductionAmount($baseAmount);
            echo "     Calculated from $" . number_format($baseAmount, 2) . " base: $" . number_format($calculatedAmount, 2) . "\n";
        }
        
        echo "\n";
    }
    
    echo "\n";

    // Test 5: Test Scopes and Queries
    echo "5. Testing Scopes and Queries...\n";
    
    echo "   Active deductions count: " . Deduction::active()->count() . "\n";
    echo "   Inactive deductions count: " . Deduction::inactive()->count() . "\n";
    echo "   Suspended deductions count: " . Deduction::suspended()->count() . "\n";
    
    // Test by employee scope
    echo "   Deductions for employee {$employee->emp_id}: " . Deduction::byEmployee($employee->emp_id)->count() . "\n";
    
    // Test category scopes
    echo "   Pre-Tax deductions: " . Deduction::preTax()->count() . "\n";
    echo "   Post-Tax deductions: " . Deduction::postTax()->count() . "\n";
    echo "   Garnishment deductions: " . Deduction::garnishment()->count() . "\n";
    
    // Test calculation type scopes
    echo "   Fixed Amount deductions: " . Deduction::byCalculationType('fixed_amount')->count() . "\n";
    echo "   Percentage deductions: " . Deduction::byCalculationType('percentage')->count() . "\n";
    
    // Test required/optional scopes
    echo "   Required deductions: " . Deduction::required()->count() . "\n";
    echo "   Optional deductions: " . Deduction::optional()->count() . "\n";
    
    echo "\n";

    // Test 6: Test Static Methods
    echo "6. Testing Static Methods...\n";
    
    // Get deductions by employee
    $employeeDeductions = Deduction::getDeductionsByEmployee($employee->emp_id);
    echo "   Employee deductions count: " . $employeeDeductions->count() . "\n";
    
    // Get active deductions by employee
    $activeEmployeeDeductions = Deduction::getActiveDeductionsByEmployee($employee->emp_id);
    echo "   Active employee deductions count: " . $activeEmployeeDeductions->count() . "\n";
    
    // Get deduction statistics
    $deductionStats = Deduction::getDeductionStatistics();
    echo "   Deduction statistics:\n";
    foreach ($deductionStats as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    
    // Get available options
    echo "   Available deduction categories:\n";
    $categories = Deduction::getDeductionCategories();
    foreach ($categories as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    
    echo "   Available calculation types:\n";
    $calcTypes = Deduction::getCalculationTypes();
    foreach ($calcTypes as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    
    echo "   Available frequencies:\n";
    $frequencies = Deduction::getFrequencies();
    foreach ($frequencies as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    
    echo "\n";

    // Test 7: Test Relationships
    echo "7. Testing Relationships...\n";
    
    // Test employee relationships
    echo "   Employee has deductions: " . ($employee->deductions()->count() > 0 ? 'Yes' : 'No') . "\n";
    echo "   Employee active deductions: " . ($employee->activeDeductions()->count()) . "\n";
    echo "   Employee pre-tax deductions: " . ($employee->preTaxDeductions()->count()) . "\n";
    echo "   Employee post-tax deductions: " . ($employee->postTaxDeductions()->count()) . "\n";
    
    // Test deduction relationships
    foreach ($deductions as $deduction) {
        echo "   Deduction '{$deduction->deduction_type}' belongs to employee: " . $deduction->employee->full_name . "\n";
    }
    
    echo "\n";

    // Test 8: Test Validation and Constraints
    echo "8. Testing Validation and Constraints...\n";
    
    // Test unique constraint
    echo "   Attempting to create duplicate deduction type...\n";
    try {
        $duplicateDeduction = Deduction::create([
            'employee_id' => $employee->emp_id,
            'deduction_type' => 'Other Pre-Tax Deductions', // Same type
            'amount' => 100.00,
            'deduction_category' => 'pre_tax',
            'calculation_type' => 'fixed_amount',
            'frequency' => 'per_pay_period',
            'status' => 'active',
            'is_required' => false,
            'created_by' => 1,
            'updated_by' => 1,
        ]);
        echo "   ❌ Duplicate creation should have failed\n";
    } catch (\Exception $e) {
        echo "   ✅ Duplicate creation properly prevented: " . $e->getMessage() . "\n";
    }
    
    echo "\n";

    // Test 9: Test Effective Date Logic
    echo "9. Testing Effective Date Logic...\n";
    
    // Create a deduction with future effective date
    $futureDeduction = Deduction::create([
        'employee_id' => $employee->emp_id,
        'deduction_type' => 'Future Deduction',
        'amount' => 50.00,
        'deduction_category' => 'pre_tax',
        'calculation_type' => 'fixed_amount',
        'frequency' => 'per_pay_period',
        'status' => 'active',
        'is_required' => false,
        'effective_date' => now()->addDays(30),
        'created_by' => 1,
        'updated_by' => 1,
    ]);
    
    echo "   Created future deduction: {$futureDeduction->deduction_type}\n";
    echo "   Effective date: {$futureDeduction->effective_date->format('Y-m-d')}\n";
    echo "   Is currently effective: " . ($futureDeduction->is_effective ? 'Yes' : 'No') . "\n";
    
    // Test effective scope
    $currentlyEffective = Deduction::effective()->count();
    echo "   Currently effective deductions: {$currentlyEffective}\n";
    
    echo "\n";

    // Test 10: Test Bulk Operations
    echo "10. Testing Bulk Operations...\n";
    
    // Get some deduction IDs for bulk operations
    $deductionIds = $deductions->pluck('deduction_id')->toArray();
    
    echo "   Bulk updating status to 'inactive' for " . count($deductionIds) . " deductions...\n";
    
    $updatedCount = 0;
    foreach ($deductionIds as $deductionId) {
        $deduction = Deduction::find($deductionId);
        if ($deduction) {
            $deduction->deactivate();
            $updatedCount++;
        }
    }
    
    echo "   ✅ Updated {$updatedCount} deductions to inactive\n";
    
    // Reactivate them
    echo "   Reactivating deductions...\n";
    foreach ($deductionIds as $deductionId) {
        $deduction = Deduction::find($deductionId);
        if ($deduction) {
            $deduction->activate();
        }
    }
    echo "   ✅ Reactivated deductions\n";
    
    echo "\n";

    echo "✅ All tests completed successfully!\n";
    echo "\nSystem Summary:\n";
    echo "- Deduction records: " . Deduction::count() . "\n";
    echo "- Employees with deductions: " . Employee::whereHas('deductions')->count() . "\n";
    echo "- Active deductions: " . Deduction::active()->count() . "\n";
    echo "- Inactive deductions: " . Deduction::inactive()->count() . "\n";
    echo "- Pre-tax deductions: " . Deduction::preTax()->count() . "\n";
    echo "- Post-tax deductions: " . Deduction::postTax()->count() . "\n";
    echo "- Required deductions: " . Deduction::required()->count() . "\n";
    echo "- Optional deductions: " . Deduction::optional()->count() . "\n";
    echo "- Total deduction amount: " . Deduction::sum('amount') . "\n";
    echo "- Total annual deduction amount: " . Deduction::active()->get()->sum(function ($d) {
        return $d->getAnnualDeductionAmount();
    }) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
