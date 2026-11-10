<?php

require_once 'vendor/autoload.php';

use App\Models\PayTemplate;
use App\Models\PayTemplateEarning;
use App\Models\PayTemplateAuditLog;
use App\Models\Employee;

echo "Testing Pay Template System\n";
echo "===========================\n\n";

try {
    // Test 1: Create Pay Template with Earnings
    echo "1. Testing Pay Template Creation...\n";
    
    // Get an employee for testing
    $employee = Employee::first();
    if (!$employee) {
        echo "   ⚠️  No employees found for testing\n";
        exit;
    }
    
    echo "   Using employee: {$employee->full_name} (ID: {$employee->emp_id})\n";
    
    // Check if pay template already exists for this employee
    $existingTemplate = PayTemplate::where('employee_id', $employee->emp_id)->first();
    if ($existingTemplate) {
        echo "   ⚠️  Pay template already exists for this employee\n";
        $payTemplate = $existingTemplate;
    } else {
        // Create new pay template with earnings matching the form fields from the image
        $payTemplate = PayTemplate::create([
            'employee_id' => $employee->emp_id,
            'template_name' => 'Standard Pay Template',
            'description' => 'Default pay template for regular employees',
            'status' => 'active',
            'created_by' => 1, // Assuming user ID 1 exists
            'updated_by' => 1,
        ]);
        echo "   ✅ Created pay template for {$employee->full_name}\n";
    }
    
    echo "\n";

    // Test 2: Create Earnings Lines (matching the image)
    echo "2. Testing Earnings Lines Creation...\n";
    
    // Check if earnings already exist
    if ($payTemplate->earnings()->count() > 0) {
        echo "   ⚠️  Earnings already exist for this template\n";
        $earnings = $payTemplate->earnings;
    } else {
        // Create earnings matching the form fields from the image
        $earningsData = [
            [
                'earnings_rate_name' => 'Ordinary Hours',
                'calculation_type' => 'hours_rate',
                'estimate_rate' => 30.000000,
                'hours' => 76.0000,
                'rate' => 30.000000,
                'total' => 2280.00,
                'is_active' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'earnings_rate_name' => 'Director Fees (First Instance)',
                'calculation_type' => 'fixed_amount',
                'estimate_rate' => 1000.00,
                'fixed_amount' => 1000.00,
                'total' => 1000.00,
                'is_active' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'earnings_rate_name' => 'Lumpsum W',
                'calculation_type' => 'fixed_amount',
                'estimate_rate' => 500.00,
                'fixed_amount' => 500.00,
                'total' => 500.00,
                'is_active' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'earnings_rate_name' => 'Worker Compensation',
                'calculation_type' => 'fixed_amount',
                'estimate_rate' => 800.00,
                'fixed_amount' => 800.00,
                'total' => 800.00,
                'is_active' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'earnings_rate_name' => 'Allowances exempt from tax withholding and super',
                'calculation_type' => 'mixed',
                'estimate_rate' => 0.00,
                'mixed_fixed_amount' => 0.00,
                'mixed_rate' => 0.00,
                'mixed_total' => 0.00,
                'total' => 0.00,
                'is_active' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'earnings_rate_name' => 'Director Fees (Second Instance)',
                'calculation_type' => 'fixed_amount',
                'estimate_rate' => 0.00,
                'fixed_amount' => 0.00,
                'total' => 0.00,
                'is_active' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        $earnings = [];
        foreach ($earningsData as $index => $earningData) {
            $earningData['pay_template_id'] = $payTemplate->pay_template_id;
            $earningData['display_order'] = $index;
            
            $earning = PayTemplateEarning::create($earningData);
            $earnings[] = $earning;
            echo "   ✅ Created earning: {$earning->earnings_rate_name}\n";
        }
    }
    
    echo "\n";

    // Test 3: Test Template Details and Accessors
    echo "3. Testing Template Details and Accessors...\n";
    
    echo "   Template Name: {$payTemplate->template_name}\n";
    echo "   Description: {$payTemplate->description}\n";
    echo "   Status: {$payTemplate->status}\n";
    echo "   Status Label: {$payTemplate->status_label}\n";
    echo "   Is Active: " . ($payTemplate->is_active ? 'Yes' : 'No') . "\n";
    echo "   Is Draft: " . ($payTemplate->is_draft ? 'Yes' : 'No') . "\n";
    echo "   Total Earnings: {$payTemplate->total_earnings}\n";
    echo "   Total Hours: {$payTemplate->total_hours}\n";
    echo "   Total Fixed Amounts: {$payTemplate->total_fixed_amounts}\n";
    echo "   Earnings Count: {$payTemplate->earnings_count}\n";
    
    echo "\n";

    // Test 4: Test Earnings Details and Accessors
    echo "4. Testing Earnings Details and Accessors...\n";
    
    foreach ($payTemplate->earnings as $index => $earning) {
        $earningNumber = $index + 1;
        echo "   Earning {$earningNumber}: {$earning->earnings_rate_name}\n";
        echo "     Calculation Type: {$earning->calculation_type_label}\n";
        echo "     Estimate Rate: {$earning->formatted_estimate_rate}\n";
        
        if ($earning->is_hours_rate) {
            echo "     Hours: {$earning->formatted_hours}\n";
            echo "     Rate: {$earning->formatted_rate}\n";
        } elseif ($earning->is_fixed_amount) {
            echo "     Fixed Amount: {$earning->formatted_fixed_amount}\n";
        } elseif ($earning->is_mixed) {
            $mixedFixedAmount = number_format($earning->mixed_fixed_amount, 2);
            $mixedRate = number_format($earning->mixed_rate, 6);
            echo "     Mixed Fixed Amount: \${$mixedFixedAmount}\n";
            echo "     Mixed Rate: {$mixedRate}\n";
        }
        
        echo "     Total: {$earning->formatted_total}\n";
        echo "     Is Active: " . ($earning->is_active ? 'Yes' : 'No') . "\n";
        echo "     Display Order: {$earning->display_order}\n";
        echo "\n";
    }
    
    echo "\n";

    // Test 5: Test Template Management Methods
    echo "5. Testing Template Management Methods...\n";
    
    // Test status changes
    echo "   Setting template as draft...\n";
    $payTemplate->setAsDraft();
    echo "   ✅ Template status: {$payTemplate->status}\n";
    
    echo "   Activating template...\n";
    $payTemplate->activate();
    echo "   ✅ Template status: {$payTemplate->status}\n";
    
    // Test adding new earning
    echo "   Adding new earnings line...\n";
    $newEarning = $payTemplate->addEarning([
        'earnings_rate_name' => 'Bonus Payment',
        'calculation_type' => 'fixed_amount',
        'estimate_rate' => 500.00,
        'fixed_amount' => 500.00,
        'is_active' => true,
        'created_by' => 1,
        'updated_by' => 1,
    ]);
    echo "   ✅ Added new earning: {$newEarning->earnings_rate_name}\n";
    
    echo "\n";

    // Test 6: Test Earnings Management Methods
    echo "6. Testing Earnings Management Methods...\n";
    
    // Test calculation type changes
    $firstEarning = $payTemplate->earnings->first();
    echo "   Testing calculation type changes for: {$firstEarning->earnings_rate_name}\n";
    
    echo "     Current type: {$firstEarning->calculation_type_label}\n";
    echo "     Setting to fixed amount...\n";
    $firstEarning->setCalculationType('fixed_amount');
    echo "     New type: {$firstEarning->calculation_type_label}\n";
    
    echo "     Setting back to hours & rate...\n";
    $firstEarning->setCalculationType('hours_rate');
    echo "     Final type: {$firstEarning->calculation_type_label}\n";
    
    // Test total calculations
    echo "     Updating hours and rate...\n";
    $firstEarning->updateHoursAndRate(80.0000, 35.000000);
    echo "     New total: {$firstEarning->formatted_total}\n";
    
    echo "\n";

    // Test 7: Test Scopes and Queries
    echo "7. Testing Scopes and Queries...\n";
    
    echo "   Active templates count: " . PayTemplate::active()->count() . "\n";
    echo "   Inactive templates count: " . PayTemplate::inactive()->count() . "\n";
    echo "   Draft templates count: " . PayTemplate::draft()->count() . "\n";
    
    // Test by employee scope
    echo "   Templates for employee {$employee->emp_id}: " . PayTemplate::byEmployee($employee->emp_id)->count() . "\n";
    
    // Test earnings scopes
    echo "   Hours & Rate earnings: " . PayTemplateEarning::hoursRate()->count() . "\n";
    echo "   Fixed Amount earnings: " . PayTemplateEarning::fixedAmount()->count() . "\n";
    echo "   Mixed earnings: " . PayTemplateEarning::mixed()->count() . "\n";
    
    echo "\n";

    // Test 8: Test Static Methods
    echo "8. Testing Static Methods...\n";
    
    // Get templates by employee
    $employeeTemplates = PayTemplate::getByEmployee($employee->emp_id);
    echo "   Employee templates count: " . $employeeTemplates->count() . "\n";
    
    // Get active template by employee
    $activeTemplate = PayTemplate::getActiveByEmployee($employee->emp_id);
    echo "   Active template: " . ($activeTemplate ? $activeTemplate->template_name : 'None') . "\n";
    
    // Get template statistics
    $templateStats = PayTemplate::getTemplateStatistics();
    echo "   Template statistics:\n";
    foreach ($templateStats as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    
    echo "\n";

    // Test 9: Test Audit Logging
    echo "9. Testing Audit Logging...\n";
    
    // Get audit trail for pay template
    $templateAuditTrail = PayTemplateAuditLog::getAuditTrail(
        PayTemplateAuditLog::TABLE_PAY_TEMPLATES,
        $payTemplate->pay_template_id
    );
    echo "   Template audit records: " . $templateAuditTrail->count() . "\n";
    
    // Get audit trail for earnings
    $earningsAuditTrail = PayTemplateAuditLog::getAuditTrail(
        PayTemplateAuditLog::TABLE_PAY_TEMPLATE_EARNINGS,
        $firstEarning->earnings_id
    );
    echo "   Earnings audit records: " . $earningsAuditTrail->count() . "\n";
    
    // Get system activity
    $systemActivity = PayTemplateAuditLog::getSystemActivity(7); // Last 7 days
    echo "   System activity (7 days): " . $systemActivity->count() . "\n";
    
    // Get activity statistics
    $activityStats = PayTemplateAuditLog::getActivityStatistics(7);
    echo "   Activity statistics (7 days):\n";
    foreach ($activityStats as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    
    echo "\n";

    // Test 10: Test Relationships
    echo "10. Testing Relationships...\n";
    
    echo "   Employee name: {$payTemplate->employee->full_name}\n";
    echo "   Employee email: {$payTemplate->employee->emp_email_address}\n";
    
    // Test employee relationships
    echo "   Employee has pay templates: " . ($employee->payTemplates()->count() > 0 ? 'Yes' : 'No') . "\n";
    
    // Test earnings relationships
    foreach ($payTemplate->earnings as $earning) {
        echo "   Earning '{$earning->earnings_rate_name}' belongs to template: " . $earning->payTemplate->template_name . "\n";
    }
    
    echo "\n";

    // Test 11: Test Template Operations
    echo "11. Testing Template Operations...\n";
    
    // Test duplication
    echo "   Duplicating template...\n";
    $duplicateTemplate = $payTemplate->duplicate();
    echo "   ✅ Duplicated template ID: {$duplicateTemplate->pay_template_id}\n";
    echo "   ✅ Duplicate earnings count: " . $duplicateTemplate->earnings()->count() . "\n";
    
    // Test earnings reordering
    echo "   Testing earnings reordering...\n";
    $payTemplate->reorderEarnings();
    echo "   ✅ Earnings reordered\n";
    
    // Test total calculations
    echo "   Recalculating totals...\n";
    $payTemplate->calculateTotals();
    echo "   ✅ Totals recalculated\n";
    
    echo "\n";

    // Test 12: Test Validation and Constraints
    echo "12. Testing Validation and Constraints...\n";
    
    // Test calculation type validation
    $validTypes = PayTemplateEarning::getCalculationTypes();
    echo "   Valid calculation types:\n";
    foreach ($validTypes as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    
    // Test calculation type validation
    $isValid = PayTemplateEarning::validateCalculationType('hours_rate');
    echo "   'hours_rate' is valid: " . ($isValid ? 'Yes' : 'No') . "\n";
    
    $isInvalid = PayTemplateEarning::validateCalculationType('invalid_type');
    echo "   'invalid_type' is valid: " . ($isInvalid ? 'Yes' : 'No') . "\n";
    
    echo "\n";

    echo "✅ All tests completed successfully!\n";
    echo "\nSystem Summary:\n";
    echo "- Pay Template records: " . PayTemplate::count() . "\n";
    echo "- Pay Template Earnings records: " . PayTemplateEarning::count() . "\n";
    echo "- Audit Log records: " . PayTemplateAuditLog::count() . "\n";
    echo "- Employees with pay templates: " . Employee::whereHas('payTemplates')->count() . "\n";
    echo "- Active pay templates: " . PayTemplate::active()->count() . "\n";
    echo "- Draft pay templates: " . PayTemplate::draft()->count() . "\n";
    echo "- Total earnings across all templates: " . PayTemplateEarning::sum('total') . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
