<?php

require_once 'vendor/autoload.php';

use App\Models\BankAccount;
use App\Models\Employee;

echo "Testing Bank Account System\n";
echo "===========================\n\n";

try {
    // Test 1: Create Bank Account
    echo "1. Testing Bank Account Creation...\n";
    
    // Get an employee for testing
    $employee = Employee::first();
    if (!$employee) {
        echo "   ⚠️  No employees found for testing\n";
        exit;
    }
    
    echo "   Using employee: {$employee->full_name} (ID: {$employee->emp_id})\n";
    
    // Check if bank account already exists for this employee
    $existingAccount = BankAccount::where('employee_id', $employee->emp_id)->first();
    if ($existingAccount) {
        echo "   ⚠️  Bank account already exists for this employee\n";
        $bankAccount = $existingAccount;
    } else {
        // Create new bank account matching the form fields from the image
        $bankAccount = BankAccount::create([
            'employee_id' => $employee->emp_id,
            'statement_text' => 'Salary',
            'account_name' => 'O Gray',
            'bsb_number' => '234567',
            'account_number' => '123451222',
            'balance' => 0.00,
            'is_primary_account' => true,
            'status' => 'active',
            'created_by' => 1, // Assuming user ID 1 exists
            'updated_by' => 1,
        ]);
        echo "   ✅ Created bank account for {$employee->full_name}\n";
    }
    
    echo "\n";

    // Test 2: Test Account Details and Accessors
    echo "2. Testing Account Details and Accessors...\n";
    
    echo "   Statement Text: {$bankAccount->statement_text}\n";
    echo "   Account Name: {$bankAccount->account_name}\n";
    echo "   BSB Number: {$bankAccount->bsb_number}\n";
    echo "   Formatted BSB: {$bankAccount->formatted_bsb}\n";
    echo "   Account Number: {$bankAccount->account_number}\n";
    echo "   Formatted Account Number: {$bankAccount->formatted_account_number}\n";
    echo "   Balance: {$bankAccount->balance}\n";
    echo "   Formatted Balance: {$bankAccount->formatted_balance}\n";
    echo "   Is Primary Account: " . ($bankAccount->is_primary_account ? 'Yes' : 'No') . "\n";
    echo "   Status: {$bankAccount->status}\n";
    echo "   Account Display Name: {$bankAccount->account_display_name}\n";
    
    echo "\n";

    // Test 3: Test Account Management Methods
    echo "3. Testing Account Management Methods...\n";
    
    // Test setting as primary
    echo "   Setting account as primary...\n";
    $bankAccount->setAsPrimary();
    echo "   ✅ Account is now primary: " . ($bankAccount->is_primary_account ? 'Yes' : 'No') . "\n";
    
    // Test balance updates
    echo "   Updating balance to $1000.00...\n";
    $bankAccount->updateBalance(1000.00);
    echo "   ✅ New balance: {$bankAccount->formatted_balance}\n";
    
    echo "   Adding $500.00 to balance...\n";
    $bankAccount->addToBalance(500.00);
    echo "   ✅ New balance: {$bankAccount->formatted_balance}\n";
    
    echo "   Deducting $200.00 from balance...\n";
    $bankAccount->deductFromBalance(200.00);
    echo "   ✅ New balance: {$bankAccount->formatted_balance}\n";
    
    // Test statement text update
    echo "   Updating statement text to 'Pay Cheque'...\n";
    $bankAccount->updateStatementText('Pay Cheque');
    echo "   ✅ New statement text: {$bankAccount->statement_text}\n";
    
    // Test account details update
    echo "   Updating account details...\n";
    $bankAccount->updateAccountDetails('New Account Name', '123456', '987654321');
    echo "   ✅ New account name: {$bankAccount->account_name}\n";
    echo "   ✅ New BSB: {$bankAccount->bsb_number}\n";
    echo "   ✅ New account number: {$bankAccount->account_number}\n";
    
    echo "\n";

    // Test 4: Test Scopes and Queries
    echo "4. Testing Scopes and Queries...\n";
    
    echo "   Active accounts count: " . BankAccount::active()->count() . "\n";
    echo "   Inactive accounts count: " . BankAccount::inactive()->count() . "\n";
    echo "   Primary accounts count: " . BankAccount::primary()->count() . "\n";
    
    // Test by employee scope
    echo "   Accounts for employee {$employee->emp_id}: " . BankAccount::byEmployee($employee->emp_id)->count() . "\n";
    
    echo "\n";

    // Test 5: Test Static Methods
    echo "5. Testing Static Methods...\n";
    
    // Get primary account
    $primaryAccount = BankAccount::getPrimaryAccount($employee->emp_id);
    echo "   Primary account: " . ($primaryAccount ? $primaryAccount->account_name : 'None') . "\n";
    
    // Get active accounts
    $activeAccounts = BankAccount::getActiveAccounts($employee->emp_id);
    echo "   Active accounts count: " . $activeAccounts->count() . "\n";
    
    // Get all accounts by employee
    $allAccounts = BankAccount::getAccountsByEmployee($employee->emp_id);
    echo "   All accounts count: " . $allAccounts->count() . "\n";
    
    echo "\n";

    // Test 6: Test Validation Methods
    echo "6. Testing Validation Methods...\n";
    
    // Test BSB validation
    $validBSB = BankAccount::validateBSB('234567');
    echo "   BSB 234567 is valid: " . ($validBSB ? 'Yes' : 'No') . "\n";
    
    $invalidBSB = BankAccount::validateBSB('12345');
    echo "   BSB 12345 is valid: " . ($invalidBSB ? 'Yes' : 'No') . "\n";
    
    // Test account number validation
    $validAccount = BankAccount::validateAccountNumber('123451222');
    echo "   Account 123451222 is valid: " . ($validAccount ? 'Yes' : 'No') . "\n";
    
    $invalidAccount = BankAccount::validateAccountNumber('123');
    echo "   Account 123 is valid: " . ($invalidAccount ? 'Yes' : 'No') . "\n";
    
    echo "\n";

    // Test 7: Test Relationships
    echo "7. Testing Relationships...\n";
    
    echo "   Employee name: {$bankAccount->employee->full_name}\n";
    echo "   Employee email: {$bankAccount->employee->emp_email_address}\n";
    
    // Test employee relationships
    echo "   Employee has bank accounts: " . ($employee->bankAccounts()->count() > 0 ? 'Yes' : 'No') . "\n";
    echo "   Employee primary bank account: " . ($employee->primaryBankAccount ? $employee->primaryBankAccount->account_name : 'None') . "\n";
    
    echo "\n";

    // Test 8: Test Statistics
    echo "8. Testing Statistics...\n";
    
    $stats = BankAccount::getAccountStatistics();
    echo "   Total accounts: {$stats['total_accounts']}\n";
    echo "   Active accounts: {$stats['active_accounts']}\n";
    echo "   Inactive accounts: {$stats['inactive_accounts']}\n";
    echo "   Primary accounts: {$stats['primary_accounts']}\n";
    echo "   Total balance: {$stats['total_balance']}\n";
    echo "   Average balance: {$stats['average_balance']}\n";
    
    echo "\n";

    // Test 9: Test Account Operations
    echo "9. Testing Account Operations...\n";
    
    // Test activation/deactivation
    echo "   Deactivating account...\n";
    $bankAccount->deactivate();
    echo "   ✅ Account active: " . ($bankAccount->isActive() ? 'Yes' : 'No') . "\n";
    
    echo "   Activating account...\n";
    $bankAccount->activate();
    echo "   ✅ Account active: " . ($bankAccount->isActive() ? 'Yes' : 'No') . "\n";
    
    echo "\n";

    // Test 10: Create Additional Account (Bank Account 2 from image)
    echo "10. Testing Multiple Accounts...\n";
    
    // Create a second account for the same employee
    $secondAccount = BankAccount::create([
        'employee_id' => $employee->emp_id,
        'statement_text' => 'Pay Cheque',
        'account_name' => 'Secondary Account',
        'bsb_number' => '000000',
        'account_number' => '000000000',
        'balance' => 0.00,
        'is_primary_account' => false,
        'status' => 'active',
        'created_by' => 1,
        'updated_by' => 1,
    ]);
    echo "   ✅ Created second bank account\n";
    
    echo "   Total accounts for employee: " . BankAccount::byEmployee($employee->emp_id)->count() . "\n";
    echo "   Primary account: " . BankAccount::getPrimaryAccount($employee->emp_id)->account_name . "\n";
    
    echo "\n";

    echo "✅ All tests completed successfully!\n";
    echo "\nSystem Summary:\n";
    echo "- Bank Account records: " . BankAccount::count() . "\n";
    echo "- Employees with bank accounts: " . Employee::whereHas('bankAccounts')->count() . "\n";
    echo "- Active bank accounts: " . BankAccount::active()->count() . "\n";
    echo "- Primary accounts: " . BankAccount::primary()->count() . "\n";
    echo "- Total balance across all accounts: $" . number_format(BankAccount::sum('balance'), 2) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
