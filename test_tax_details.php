<?php

require_once 'vendor/autoload.php';

use App\Models\TaxDetail;
use App\Models\Employee;

echo "Testing Tax Details System\n";
echo "==========================\n\n";

try {
    // Test 1: Create Tax Details
    echo "1. Testing Tax Details Creation...\n";
    
    // Get an employee for testing
    $employee = Employee::first();
    if (!$employee) {
        echo "   ⚠️  No employees found for testing\n";
        exit;
    }
    
    echo "   Using employee: {$employee->full_name} (ID: {$employee->emp_id})\n";
    
    // Check if tax details already exist for this employee
    $existingTaxDetails = TaxDetail::where('employee_id', $employee->emp_id)->first();
    if ($existingTaxDetails) {
        echo "   ⚠️  Tax details already exist for this employee\n";
        $taxDetails = $existingTaxDetails;
    } else {
        // Create new tax details
        $taxDetails = TaxDetail::create([
            'employee_id' => $employee->emp_id,
            'tax_file_number' => '123456789',
            'tfn_exemption' => null,
            'residency_status' => 'Australian resident',
            'has_approved_withholding_variation' => false,
            'tax_scale_type' => 'Regular',
            'has_study_training_loans' => false,
            'claim_tax_free_threshold' => true,
            'other_tax_offset_claimed' => false,
            'tax_offset_amount' => 0.00,
            'increase_tax_withheld' => false,
            'additional_tax_withheld' => 0.00,
            'eligible_for_leave_loading' => false,
            'tax_notes' => 'Test tax details',
            'is_active' => true,
        ]);
        echo "   ✅ Created tax details for {$employee->full_name}\n";
    }
    
    echo "\n";

    // Test 2: Test TFN Management
    echo "2. Testing TFN Management...\n";
    
    echo "   Current TFN: {$taxDetails->tax_file_number}\n";
    echo "   Formatted TFN: {$taxDetails->formatted_tfn}\n";
    echo "   Has TFN: " . ($taxDetails->has_tfn ? 'Yes' : 'No') . "\n";
    
    // Test TFN validation
    echo "   Testing TFN validation...\n";
    $validTFN = TaxDetail::validateTFN('123456789');
    echo "   TFN 123456789 is valid: " . ($validTFN ? 'Yes' : 'No') . "\n";
    
    $invalidTFN = TaxDetail::validateTFN('12345678');
    echo "   TFN 12345678 is valid: " . ($invalidTFN ? 'Yes' : 'No') . "\n";
    
    // Test setting new TFN
    echo "   Setting new TFN 987654321...\n";
    $taxDetails->setTFN('987654321');
    echo "   ✅ New TFN set: {$taxDetails->tax_file_number}\n";
    
    // Test clearing TFN
    echo "   Clearing TFN...\n";
    $taxDetails->clearTFN();
    echo "   ✅ TFN cleared. Has TFN: " . ($taxDetails->has_tfn ? 'Yes' : 'No') . "\n";
    
    // Test setting TFN exemption
    echo "   Setting TFN exemption to 'Student'...\n";
    $taxDetails->setTFNExemption('Student');
    echo "   ✅ TFN exemption set: {$taxDetails->tfn_exemption}\n";
    
    echo "\n";

    // Test 3: Test Residency and Tax Scale
    echo "3. Testing Residency and Tax Scale...\n";
    
    echo "   Current residency: {$taxDetails->residency_status}\n";
    echo "   Is Australian resident: " . ($taxDetails->is_australian_resident ? 'Yes' : 'No') . "\n";
    echo "   Is Foreign resident: " . ($taxDetails->is_foreign_resident ? 'Yes' : 'No') . "\n";
    
    // Test changing residency
    echo "   Changing residency to 'Foreign resident'...\n";
    $taxDetails->setResidencyStatus('Foreign resident');
    echo "   ✅ Residency changed to: {$taxDetails->residency_status}\n";
    
    // Test changing tax scale
    echo "   Current tax scale: {$taxDetails->tax_scale_type}\n";
    echo "   Changing tax scale to 'No TFN'...\n";
    $taxDetails->setTaxScale('No TFN');
    echo "   ✅ Tax scale changed to: {$taxDetails->tax_scale_type}\n";
    
    echo "\n";

    // Test 4: Test Tax Offset and Withholding
    echo "4. Testing Tax Offset and Withholding...\n";
    
    // Test tax offset
    echo "   Setting tax offset to $500.00...\n";
    $taxDetails->setTaxOffset(500.00);
    echo "   ✅ Tax offset set: $" . number_format($taxDetails->tax_offset_amount, 2) . "\n";
    echo "   Tax offset per pay period: $" . number_format($taxDetails->tax_offset_per_pay_period, 2) . "\n";
    
    // Test additional withholding
    echo "   Setting additional withholding to $50.00...\n";
    $taxDetails->setAdditionalWithholding(50.00);
    echo "   ✅ Additional withholding set: $" . number_format($taxDetails->additional_tax_withheld, 2) . "\n";
    echo "   Total tax withheld per pay period: $" . number_format($taxDetails->total_tax_withheld_per_pay_period, 2) . "\n";
    
    echo "\n";

    // Test 5: Test Boolean Settings
    echo "5. Testing Boolean Settings...\n";
    
    // Test study loans
    echo "   Current study loans: " . ($taxDetails->has_study_training_loans ? 'Yes' : 'No') . "\n";
    echo "   Toggling study loans...\n";
    $taxDetails->toggleStudyLoans();
    echo "   ✅ Study loans toggled to: " . ($taxDetails->has_study_training_loans ? 'Yes' : 'No') . "\n";
    
    // Test tax-free threshold
    echo "   Current tax-free threshold: " . ($taxDetails->claim_tax_free_threshold ? 'Yes' : 'No') . "\n";
    echo "   Toggling tax-free threshold...\n";
    $taxDetails->toggleTaxFreeThreshold();
    echo "   ✅ Tax-free threshold toggled to: " . ($taxDetails->claim_tax_free_threshold ? 'Yes' : 'No') . "\n";
    
    // Test leave loading
    echo "   Current leave loading: " . ($taxDetails->eligible_for_leave_loading ? 'Yes' : 'No') . "\n";
    echo "   Toggling leave loading...\n";
    $taxDetails->toggleLeaveLoading();
    echo "   ✅ Leave loading toggled to: " . ($taxDetails->eligible_for_leave_loading ? 'Yes' : 'No') . "\n";
    
    echo "\n";

    // Test 6: Test Withholding Variation
    echo "6. Testing Withholding Variation...\n";
    
    echo "   Current withholding variation: " . ($taxDetails->has_approved_withholding_variation ? 'Approved' : 'Not Approved') . "\n";
    echo "   Approving withholding variation...\n";
    $taxDetails->approveWithholdingVariation();
    echo "   ✅ Withholding variation approved: " . ($taxDetails->has_approved_withholding_variation ? 'Yes' : 'No') . "\n";
    
    echo "   Revoking withholding variation...\n";
    $taxDetails->revokeWithholdingVariation();
    echo "   ✅ Withholding variation revoked: " . ($taxDetails->has_approved_withholding_variation ? 'Yes' : 'No') . "\n";
    
    echo "\n";

    // Test 7: Test Scopes and Queries
    echo "7. Testing Scopes and Queries...\n";
    
    $stats = TaxDetail::getTaxStatistics();
    echo "   Tax Statistics:\n";
    echo "     - Total tax records: {$stats['total_tax_records']}\n";
    echo "     - Active tax records: {$stats['active_tax_records']}\n";
    echo "     - Australian residents: {$stats['australian_residents']}\n";
    echo "     - Foreign residents: {$stats['foreign_residents']}\n";
    echo "     - With TFN: {$stats['with_tfn']}\n";
    echo "     - Without TFN: {$stats['without_tfn']}\n";
    echo "     - With study loans: {$stats['with_study_loans']}\n";
    echo "     - With tax offset: {$stats['with_tax_offset']}\n";
    echo "     - With additional withholding: {$stats['with_additional_withholding']}\n";
    echo "     - Eligible for leave loading: {$stats['eligible_for_leave_loading']}\n";
    echo "     - Withholding variation approved: {$stats['withholding_variation_approved']}\n";
    
    // Test specific queries
    $foreignResidents = TaxDetail::getEmployeesByResidency('Foreign resident');
    echo "   Foreign residents count: " . count($foreignResidents) . "\n";
    
    $noTFNEmployees = TaxDetail::getEmployeesByTaxScale('No TFN');
    echo "   No TFN employees count: " . count($noTFNEmployees) . "\n";
    
    $studyLoanEmployees = TaxDetail::getEmployeesWithStudyLoans();
    echo "   Study loan employees count: " . count($studyLoanEmployees) . "\n";
    
    echo "\n";

    // Test 8: Test Accessors
    echo "8. Testing Accessors...\n";
    
    echo "   Tax Status Summary:\n";
    echo "     {$taxDetails->tax_status_summary}\n";
    
    echo "   Formatted TFN: " . ($taxDetails->formatted_tfn ?? 'None') . "\n";
    echo "   Tax Offset per Pay Period: $" . number_format($taxDetails->tax_offset_per_pay_period, 2) . "\n";
    echo "   Total Tax Withheld per Pay Period: $" . number_format($taxDetails->total_tax_withheld_per_pay_period, 2) . "\n";
    
    echo "\n";

    // Test 9: Test Relationships
    echo "9. Testing Relationships...\n";
    
    $relatedEmployee = $taxDetails->employee;
    echo "   Related employee: {$relatedEmployee->full_name}\n";
    echo "   Employee company: {$relatedEmployee->company->company_name}\n";
    
    // Test reverse relationship
    $employeeTaxDetails = $employee->taxDetails;
    if ($employeeTaxDetails) {
        echo "   Employee has tax details: Yes\n";
        echo "   Tax scale: {$employeeTaxDetails->tax_scale_type}\n";
        echo "   Residency: {$employeeTaxDetails->residency_status}\n";
    } else {
        echo "   Employee has tax details: No\n";
    }
    
    echo "\n";

    // Test 10: Test Constants
    echo "10. Testing Constants...\n";
    
    echo "   TFN Exemptions:\n";
    echo "     - Working Holiday: " . TaxDetail::TFN_EXEMPTION_WORKING_HOLIDAY . "\n";
    echo "     - Student: " . TaxDetail::TFN_EXEMPTION_STUDENT . "\n";
    echo "     - Pensioner: " . TaxDetail::TFN_EXEMPTION_PENSIONER . "\n";
    echo "     - Other: " . TaxDetail::TFN_EXEMPTION_OTHER . "\n";
    
    echo "   Residency Statuses:\n";
    echo "     - Australian: " . TaxDetail::RESIDENCY_AUSTRALIAN . "\n";
    echo "     - Foreign: " . TaxDetail::RESIDENCY_FOREIGN . "\n";
    
    echo "   Tax Scale Types:\n";
    echo "     - Regular: " . TaxDetail::SCALE_REGULAR . "\n";
    echo "     - No TFN: " . TaxDetail::SCALE_NO_TFN . "\n";
    echo "     - Working Holiday: " . TaxDetail::SCALE_WORKING_HOLIDAY . "\n";
    echo "     - Student: " . TaxDetail::SCALE_STUDENT . "\n";
    echo "     - Pensioner: " . TaxDetail::SCALE_PENSIONER . "\n";
    
    echo "\n";

    echo "✅ All tests completed successfully!\n";
    echo "\nSystem Summary:\n";
    echo "- Tax Details records: " . TaxDetail::count() . "\n";
    echo "- Employees with tax details: " . Employee::whereHas('taxDetails')->count() . "\n";
    echo "- Active tax records: " . TaxDetail::active()->count() . "\n";
    echo "- Australian residents: " . TaxDetail::australianResidents()->count() . "\n";
    echo "- Foreign residents: " . TaxDetail::foreignResidents()->count() . "\n";
    echo "- With TFN: " . TaxDetail::withTFN()->count() . "\n";
    echo "- Without TFN: " . TaxDetail::withoutTFN()->count() . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
