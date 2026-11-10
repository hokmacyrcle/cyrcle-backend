<?php

require_once 'vendor/autoload.php';

use App\Models\Employee;

echo "Testing Employee Management APIs\n";
echo "================================\n\n";

try {
    // Test Employee data
    echo "1. Testing Employee Data...\n";
    
    $employees = Employee::limit(3)->get();
    echo "   Found " . $employees->count() . " employees\n";
    
    foreach ($employees as $employee) {
        echo "   - {$employee->full_name} (ID: {$employee->emp_id})\n";
        echo "     Email: {$employee->emp_email_address}\n";
        echo "     Status: {$employee->emp_status}\n";
    }
    echo "\n";

    echo "✅ All tests completed successfully!\n";
    echo "\nFinal counts:\n";
    echo "- Total Employees: " . Employee::count() . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}