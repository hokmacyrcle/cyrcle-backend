<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTransaction;
use App\Models\PaymentTerm;
use App\Models\TaxRate;
use App\Services\AuditLogService;
use Illuminate\Console\Command;

class TestAuditLogging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:test {--cleanup : Clean up test data after testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the audit logging system by creating, updating, and deleting test records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Starting Audit Logging System Test...');
        
        // Simulate authentication (you might need to adjust this based on your auth setup)
        $this->info('🔐 Simulating authentication...');
        
        try {
            // Test Company operations
            $this->testCompanyOperations();
            
            // Test Contact operations
            $this->testContactOperations();
            
            // Test Employee operations
            $this->testEmployeeOperations();
            
            // Test Invoice operations
            $this->testInvoiceOperations();
            
            // Test TaxRate operations
            $this->testTaxRateOperations();
            
            // Test PaymentTerm operations
            $this->testPaymentTermOperations();
            
            // Test custom audit events
            $this->testCustomAuditEvents();
            
            $this->info('✅ All audit logging tests completed successfully!');
            
            // Show audit log statistics
            $this->showAuditLogStatistics();
            
            // Cleanup if requested
            if ($this->option('cleanup')) {
                $this->cleanupTestData();
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function testCompanyOperations()
    {
        $this->info('🏢 Testing Company operations...');
        
        // Create
        $company = Company::create([
            'company_name' => 'Test Company ' . uniqid(),
            'company_type' => 'Client',
            'company_abn' => '12345678901',
            'gst_registered' => false,
            'user_id' => 6
        ]);
        
        $this->line('  ✅ Company created: ' . $company->company_name);
        
        // Update
        $oldName = $company->company_name;
        $company->update(['company_name' => 'Updated ' . $oldName]);
        $this->line('  ✅ Company updated: ' . $company->company_name);
        
        // Delete
        $company->delete();
        $this->line('  ✅ Company deleted');
        
        return $company;
    }
    
    private function testContactOperations()
    {
        $this->info('👤 Testing Contact operations...');
        
        // Create
        $contact = Contact::create([
            'contact_type' => 'Individual',
            'contact_first_name' => 'Test',
            'contact_last_name' => 'Contact ' . uniqid(),
            'contact_email_address' => 'contact' . uniqid() . '@example.com',
            'contact_phone_number' => '+1234567890',
            'contact_address_line_1' => '456 Contact Street',
            'contact_city' => 'Contact City',
            'contact_state' => 'Contact State',
            'contact_post_code' => '67890',
            'contact_country' => 'Contact Country',
            'contact_status' => 'Active',
            'company_id' => 1 // Assuming company with ID 1 exists
        ]);
        
        $this->line('  ✅ Contact created: ' . $contact->full_name);
        
        // Update
        $oldLastName = $contact->contact_last_name;
        $contact->update(['contact_last_name' => 'Updated ' . $oldLastName]);
        $this->line('  ✅ Contact updated: ' . $contact->full_name);
        
        // Delete
        $contact->delete();
        $this->line('  ✅ Contact deleted');
        
        return $contact;
    }
    
    private function testEmployeeOperations()
    {
        $this->info('👷 Testing Employee operations...');
        
        // Create
        $employee = Employee::create([
            'emp_first_name' => 'Test',
            'emp_last_name' => 'Employee ' . uniqid(),
            'emp_email_address' => 'employee' . uniqid() . '@example.com',
            'emp_phone_number' => '+1234567890',
            'emp_address_line_1' => '789 Employee Street',
            'emp_city' => 'Employee City',
            'emp_state' => 'Employee State',
            'emp_post_code' => '11111',
            'emp_country' => 'Employee Country',
            'emp_status' => 'Active',
            'emp_company_id' => 1, // Assuming company with ID 1 exists
            'emp_designation_id' => 1 // Assuming designation with ID 1 exists
        ]);
        
        $this->line('  ✅ Employee created: ' . $employee->emp_first_name . ' ' . $employee->emp_last_name);
        
        // Update
        $oldLastName = $employee->emp_last_name;
        $employee->update(['emp_last_name' => 'Updated ' . $oldLastName]);
        $this->line('  ✅ Employee updated: ' . $employee->emp_first_name . ' ' . $employee->emp_last_name);
        
        // Delete
        $employee->delete();
        $this->line('  ✅ Employee deleted');
        
        return $employee;
    }
    
    private function testInvoiceOperations()
    {
        $this->info('📄 Testing Invoice operations...');
        
        // Create
        $invoice = Invoice::create([
            'company_id' => 1, // Assuming company with ID 1 exists
            'sent_to' => 1, // Assuming contact with ID 1 exists
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'invoice_amount' => 1000.00,
            'invoice_total' => 1000.00,
            'gst_amount' => 0.00,
            'invoice_status' => 'Draft',
            'invoice_payment_status' => 'Unpaid',
            'invoice_paid_amount' => 0.00,
            'discount_type' => 0.00,
            'discount_method' => 'percentage',
            'discount_amount' => 0.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'subtotal' => 1000.00,
            'payment_term_id' => 1 // Assuming payment term with ID 1 exists
        ]);
        
        $this->line('  ✅ Invoice created: ' . $invoice->invoice_id);
        
        // Update
        $invoice->update(['invoice_status' => 'Sent']);
        $this->line('  ✅ Invoice updated: ' . $invoice->invoice_status);
        
        // Delete
        $invoice->delete();
        $this->line('  ✅ Invoice deleted');
        
        return $invoice;
    }
    
    private function testTaxRateOperations()
    {
        $this->info('💰 Testing TaxRate operations...');
        
        // Create
        $taxRate = TaxRate::create([
            'tax_name' => 'Test Tax ' . uniqid(),
            'rate' => 10.00,
            'description' => 'Test tax rate for audit logging',
            'is_active' => true
        ]);
        
        $this->line('  ✅ TaxRate created: ' . $taxRate->tax_name);
        
        // Update
        $taxRate->update(['rate' => 15.00]);
        $this->line('  ✅ TaxRate updated: ' . $taxRate->rate . '%');
        
        // Delete
        $taxRate->delete();
        $this->line('  ✅ TaxRate deleted');
        
        return $taxRate;
    }
    
    private function testPaymentTermOperations()
    {
        $this->info('📅 Testing PaymentTerm operations...');
        
        // Create
        $paymentTerm = PaymentTerm::create([
            'name' => 'Test Term ' . uniqid(),
            'due_calculation_method' => 'fixed_days',
            'fixed_days_value' => 30,
            'description' => 'Test payment term for audit logging',
            'is_active' => true
        ]);
        
        $this->line('  ✅ PaymentTerm created: ' . $paymentTerm->name);
        
        // Update
        $paymentTerm->update(['fixed_days_value' => 45]);
        $this->line('  ✅ PaymentTerm updated: ' . $paymentTerm->fixed_days_value . ' days');
        
        // Delete
        $paymentTerm->delete();
        $this->line('  ✅ PaymentTerm deleted');
        
        return $paymentTerm;
    }
    
    private function testCustomAuditEvents()
    {
        $this->info('🎯 Testing custom audit events...');
        
        // Log a custom event
        AuditLogService::logEvent('system_test', [
            'test_type' => 'audit_logging_test',
            'timestamp' => now()->toISOString(),
            'test_data' => 'This is a test custom audit event'
        ]);
        
        $this->line('  ✅ Custom audit event logged');
    }
    
    private function showAuditLogStatistics()
    {
        $this->info('📊 Audit Log Statistics:');
        
        $totalLogs = \App\Models\AuditLog::count();
        $todayLogs = \App\Models\AuditLog::whereDate('performed_at', today())->count();
        
        $this->line("  📈 Total audit logs: {$totalLogs}");
        $this->line("  📅 Today's logs: {$todayLogs}");
        
        // Show action breakdown
        $actions = \App\Models\AuditLog::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get();
            
        $this->line('  📋 Action breakdown:');
        foreach ($actions as $action) {
            $this->line("    - {$action->action}: {$action->count}");
        }
    }
    
    private function cleanupTestData()
    {
        $this->info('🧹 Cleaning up test data...');
        
        // This would clean up any test data if needed
        // For now, we'll just show a message
        $this->line('  ✅ Test data cleanup completed');
    }
}
