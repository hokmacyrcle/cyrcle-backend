<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Employees\EmploymentInformationInsertController;
use App\Http\Controllers\Api\Employees\EmployeeController;
use App\Http\Controllers\Api\Employees\EmployeeFormController;
use App\Http\Controllers\Api\Employees\TaxController;
use App\Http\Controllers\Api\Employees\PayTemplateController;
use App\Http\Controllers\Api\Employees\EmployeeLeaveController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\DeductionController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\CompanyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes
Route::post('/register', [App\Http\Controllers\Api\Auth\AuthController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Api\Auth\AuthController::class, 'login']);

// Firm sign-up steps 2-4 (token from /register; no company context needed yet)
Route::middleware('auth:api')->group(function () {
    Route::post('/email/send-code', [App\Http\Controllers\Api\Auth\FirmSignupController::class, 'sendCode'])->middleware('throttle:5,1');
    Route::post('/email/verify', [App\Http\Controllers\Api\Auth\FirmSignupController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::post('/firm/register', [App\Http\Controllers\Api\Auth\FirmSignupController::class, 'registerFirm']);
});

// Test routes for debugging Hostinger issues
Route::get('/test-auth', function () {
    return response()->json([
        'status' => true,
        'message' => 'Public API endpoint working',
        'timestamp' => now()
    ]);
});

// Test route without any middleware
Route::get('/public-test', function () {
    return response()->json([
        'status' => true,
        'message' => 'Public endpoint - no middleware',
        'timestamp' => now(),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version()
    ]);
});

// Debug route to check token without middleware
Route::get('/debug-token', function (Request $request) {
    $headers = $request->headers->all();
    $authHeader = $request->header('Authorization');
    
    return response()->json([
        'status' => true,
        'message' => 'Token debug info',
        'has_authorization_header' => !empty($authHeader),
        'authorization_header' => $authHeader,
        'all_headers' => $headers,
        'bearer_token' => $request->bearerToken(),
        'timestamp' => now()
    ]);
});

Route::get('/ping', function () {
    return response()->json([
        'status' => 'pong',
        'message' => 'API is responding',
        'server_time' => now()->toISOString()
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'environment' => app()->environment(),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version()
    ]);
});

// Forgot password routes
Route::post('/forgot-password/send-otp', [App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'sendOTP']);
Route::post('/forgot-password/resend-otp', [App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'resendOTP']);
Route::post('/forgot-password/verify-otp', [App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'verifyOTP']);
Route::post('/forgot-password/reset', [App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'resetPassword']);



Route::middleware(['auth:api', 'company.context', 'role.access'])->group(function () {
    // Simple test route to check if auth:api middleware is working
    Route::get('/auth-test', function (Request $request) {
        return response()->json([
            'status' => true,
            'message' => 'Authentication middleware is working',
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'timestamp' => now()
        ]);
    });
    
    // Test route to check company context
    Route::get('/company-context-test', function (Request $request) {
        $user = $request->user();
        
        // Debug information
        $token = $request->bearerToken();
        $tokenInfo = null;
        
        if ($token) {
            $tokenInfo = \DB::table('oauth_access_tokens')
                ->where('id', $token)
                ->first();
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Company context test',
            'user_id' => $user->id,
            'user_email' => $user->email,
            'company_id_from_token' => $user->getCompanyIdFromToken(),
            'company_id_dynamic_property' => $user->company_id ?? 'Not set',
            'company_id_from_request' => $request->get('company_id'),
            'bearer_token' => $token ? substr($token, 0, 20) . '...' : 'Not provided',
            'token_info' => $tokenInfo ? [
                'id' => $tokenInfo->id,
                'name' => $tokenInfo->name,
                'user_id' => $tokenInfo->user_id,
                'revoked' => $tokenInfo->revoked
            ] : 'Token not found',
            'timestamp' => now()
        ]);
    });
    
    // Test route to create a token (for debugging)
    Route::get('/create-test-token', function (Request $request) {
        $user = $request->user();
        $token = $user->createToken('TestToken-' . time());
        
    return response()->json([
        'status' => true,
            'message' => 'Test token created',
            'token_id' => $token->token->id,
            'token_name' => $token->token->name,
            'access_token' => $token->accessToken
    ]);
});

    // Test route to manually update token name
    Route::post('/update-token-name', function (Request $request) {
        $user = $request->user();
        $newName = $request->input('new_name', 'TestName');
        
        $token = request()->bearerToken();
        if ($token) {
            // Get current token info
            $currentToken = \DB::table('oauth_access_tokens')
                ->where('id', $token)
                ->first();
                
            $updated = \DB::table('oauth_access_tokens')
                ->where('id', $token)
                ->update(['name' => $newName]);
                
            // Get updated token info
            $updatedToken = \DB::table('oauth_access_tokens')
                ->where('id', $token)
                ->first();
                
            return response()->json([
                'status' => true,
                'message' => 'Token name updated',
                'old_name' => $currentToken ? $currentToken->name : 'Unknown',
                'new_name' => $newName,
                'rows_affected' => $updated,
                'token_id' => $token,
                'verification' => [
                    'current_name' => $updatedToken ? $updatedToken->name : 'Not found',
                    'user_id' => $updatedToken ? $updatedToken->user_id : 'Not found'
                ]
            ]);
        }
        
        return response()->json([
            'status' => false,
            'message' => 'No bearer token found'
        ], 400);
    });
    
    // Route to check current token info and company context
    Route::get('/check-token', function (Request $request) {
        $user = $request->user();
        $token = request()->bearerToken();
        
        if ($token) {
            $tokenInfo = \DB::table('oauth_access_tokens')
                ->where('id', $token)
                ->first();
                
            // Check cache for company context
            $companyId = \Cache::get("user_company_{$token}");
                
            return response()->json([
                'status' => true,
                'message' => 'Token info retrieved',
                'user_id' => $user->id,
                'bearer_token' => substr($token, 0, 20) . '...',
                'company_id_from_cache' => $companyId,
                'user_company_id_property' => $user->company_id ?? 'Not set',
                'token_info' => $tokenInfo ? [
                    'id' => $tokenInfo->id,
                    'name' => $tokenInfo->name,
                    'user_id' => $tokenInfo->user_id,
                    'revoked' => $tokenInfo->revoked,
                    'created_at' => $tokenInfo->created_at,
                    'updated_at' => $tokenInfo->updated_at
                ] : 'Token not found in DB'
            ]);
        }
        
        return response()->json([
            'status' => false,
            'message' => 'No bearer token found'
        ], 400);
    });
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Employee self-service (the only area employees can reach; see EnsureRoleAccess)
    Route::get('/me', [App\Http\Controllers\Api\MeController::class, 'show']);
    
    // Company management routes
    Route::get('/user/company', [App\Http\Controllers\Api\CompanyController::class, 'getUserCompanies']);
    Route::get('/select-company/{company_id}', [App\Http\Controllers\Api\CompanyController::class, 'selectCompany']);
    Route::get('/current-company', [App\Http\Controllers\Api\CompanyController::class, 'getCurrentCompany']);
    
    Route::post('/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout']);
    Route::post('/refresh', [App\Http\Controllers\Api\Auth\AuthController::class, 'refresh']);
    Route::put('/profile', [App\Http\Controllers\Api\Auth\AuthController::class, 'updateProfile']);

    // Client CRUD routes
    Route::apiResource('clients', App\Http\Controllers\Api\ClientController::class);
    Route::get('/clients/active/list', [App\Http\Controllers\Api\ClientController::class, 'active']);
    Route::patch('/clients/{client}/toggle-status', [App\Http\Controllers\Api\ClientController::class, 'toggleStatus']);
    Route::get('/clients/{client}/audit-history', [App\Http\Controllers\Api\ClientController::class, 'auditHistory']);

    // Contact CRUD routes with audit logging
    Route::apiResource('contacts', App\Http\Controllers\Api\ContactController::class);
    Route::get('/contacts/{contact}/audit-history', [App\Http\Controllers\Api\ContactController::class, 'auditHistory']);
    Route::post('/contacts/bulk-update-status', [App\Http\Controllers\Api\ContactController::class, 'bulkUpdateStatus']);
    Route::get('/contacts/statistics', [App\Http\Controllers\Api\ContactController::class, 'getStatistics']);
    Route::get('/contacts/test-auth', [App\Http\Controllers\Api\ContactController::class, 'testAuth']);

    // Company routes
    Route::apiResource('companies', App\Http\Controllers\Api\CompanyController::class);
    Route::patch('/companies/{company}/toggle-status', [App\Http\Controllers\Api\CompanyController::class, 'toggleStatus']);
    
    // Company attachments removed - use CommonAttachmentService instead
    
    // Bill attachments
    Route::post('/bills/{id}/attachments', [App\Http\Controllers\Api\BillController::class, 'uploadAttachments']);
    Route::get('/bills/{id}/attachments', [App\Http\Controllers\Api\BillController::class, 'getAttachments']);
    Route::delete('/bills/{id}/attachments', [App\Http\Controllers\Api\BillController::class, 'deleteAttachment']);
    Route::get('/bills/{id}/attachments/{filename}/download', [App\Http\Controllers\Api\BillController::class, 'downloadAttachment']);

    Route::apiResource('employees', App\Http\Controllers\Api\Employees\EmployeeController::class);
    Route::get('/employees-active', [App\Http\Controllers\Api\Employees\EmployeeController::class, 'active']);
    Route::patch('/employees/{employee}/toggle-status', [App\Http\Controllers\Api\Employees\EmployeeController::class, 'toggleStatus']);
    Route::get('/employees/{employee}/audit-history', [App\Http\Controllers\Api\Employees\EmployeeController::class, 'auditHistory']);
    Route::delete('/employees/{employee}', [App\Http\Controllers\Api\Employees\EmployeeController::class, 'destroy']); // explicit destroy

    // Employee details endpoints
    Route::get('/employee-details/{id}', [App\Http\Controllers\Api\Employees\EmployeeController::class, 'employee_details']);
    Route::put('/update-employee-details/{id}', [App\Http\Controllers\Api\Employees\EmployeeController::class, 'updateEmployeeDetails']);
    Route::get('/employee-dropdown', [App\Http\Controllers\Api\Employees\EmployeeController::class, 'employeeDropdown']);

    // Employee Form API routes
    Route::prefix('employee-form')->group(function () {
        Route::get('/form-data', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'getFormData']);
        Route::post('/', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'show']);
        Route::get('/{id}/edit', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'edit']);
        Route::put('/{id}', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'destroy']);
        Route::get('/search', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'search']);
        
        // Profile Image Management
        Route::post('/{id}/profile-image', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'uploadProfileImage']);
        Route::get('/{id}/profile-image', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'getProfileImage']);
        Route::delete('/{id}/profile-image', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'deleteProfileImage']);
        
        // Attachments Management
        Route::get('/{id}/attachments', [App\Http\Controllers\Api\Employees\EmployeeFormController::class, 'getAttachments']);
    });

    // Sales (Australian invoicing): the screens in Sales use these. Everything is limited to the selected business.
    // Payroll: employees of the business being worked in
    Route::prefix('payroll')->group(function () {
        Route::get('/employees', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'index']);
        Route::post('/employees', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'show'])->whereNumber('id');
        Route::put('/employees/{id}', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'update'])->whereNumber('id');
        Route::post('/employees/{id}/archive', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'setArchived'])->whereNumber('id');
        Route::get('/settings', [App\Http\Controllers\Api\Payroll\PayrollSettingsController::class, 'show']);
        Route::put('/settings', [App\Http\Controllers\Api\Payroll\PayrollSettingsController::class, 'update']);
        Route::get('/history', [App\Http\Controllers\Api\Payroll\PayHistoryController::class, 'index']);
        Route::post('/calculator', [App\Http\Controllers\Api\Payroll\PayCalculatorController::class, 'calculate']);
        Route::get('/pay-runs', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'index']);
        Route::post('/pay-runs', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'store']);
        Route::get('/pay-runs/{id}', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'show'])->whereNumber('id');
        Route::delete('/pay-runs/{id}', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'destroy'])->whereNumber('id');
        Route::post('/pay-runs/{id}/finalise', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'finalise'])->whereNumber('id');
        Route::put('/pay-runs/{id}/lines/{lineId}', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'updateLine'])->whereNumber('id')->whereNumber('lineId');
        Route::delete('/pay-runs/{id}/lines/{lineId}', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'destroyLine'])->whereNumber('id')->whereNumber('lineId');
    });

    Route::prefix('sales')->group(function () {
        Route::get('/summary', [App\Http\Controllers\Api\Sales\SummaryController::class, 'show']);
        Route::get('/business', [App\Http\Controllers\Api\Sales\SummaryController::class, 'businessInfo']);

        Route::get('/invoices', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'index']);
        Route::post('/invoices', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'store']);
        Route::get('/invoices/{id}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'show'])->whereNumber('id');
        Route::put('/invoices/{id}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'update'])->whereNumber('id');
        Route::delete('/invoices/{id}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'destroy'])->whereNumber('id');
        Route::post('/invoices/{id}/issue', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'issue'])->whereNumber('id');
        Route::post('/invoices/{id}/void', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'void'])->whereNumber('id');
        Route::post('/invoices/{id}/payments', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'addPayment'])->whereNumber('id');
        Route::delete('/invoices/{id}/payments/{transactionId}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'removePayment'])->whereNumber('id')->whereNumber('transactionId');

        Route::get('/quotes', [App\Http\Controllers\Api\Sales\QuoteController::class, 'index']);
        Route::post('/quotes', [App\Http\Controllers\Api\Sales\QuoteController::class, 'store']);
        Route::get('/quotes/{id}', [App\Http\Controllers\Api\Sales\QuoteController::class, 'show'])->whereNumber('id');
        Route::put('/quotes/{id}', [App\Http\Controllers\Api\Sales\QuoteController::class, 'update'])->whereNumber('id');
        Route::delete('/quotes/{id}', [App\Http\Controllers\Api\Sales\QuoteController::class, 'destroy'])->whereNumber('id');
        Route::post('/quotes/{id}/status', [App\Http\Controllers\Api\Sales\QuoteController::class, 'setStatus'])->whereNumber('id');
        Route::post('/quotes/{id}/convert', [App\Http\Controllers\Api\Sales\QuoteController::class, 'convert'])->whereNumber('id');

        Route::get('/customers', [App\Http\Controllers\Api\Sales\CustomerController::class, 'index']);
        Route::post('/customers', [App\Http\Controllers\Api\Sales\CustomerController::class, 'store']);
        Route::get('/customers/{id}', [App\Http\Controllers\Api\Sales\CustomerController::class, 'show'])->whereNumber('id');
        Route::put('/customers/{id}', [App\Http\Controllers\Api\Sales\CustomerController::class, 'update'])->whereNumber('id');
        Route::post('/customers/{id}/archive', [App\Http\Controllers\Api\Sales\CustomerController::class, 'setArchived'])->whereNumber('id');
    });

    // Older invoice routes (the old Sales screens). The Sales screens now use /sales/... above.
    // Only the routes that already limit data to the selected business are kept; the rest were removed because
    // they could read or change another business's invoices by id (dashboard, overdue, by-status, items,
    // transactions, attachments, discount, tax, summary, PDF export).
    Route::get('/invoices/statistics', [App\Http\Controllers\Api\InvoiceController::class, 'statistics']);
    Route::get('/invoices/form-data', [App\Http\Controllers\Api\InvoiceController::class, 'getFormData']);
    Route::apiResource('invoices', App\Http\Controllers\Api\InvoiceController::class);

    // Tax rates management
    Route::apiResource('tax-rates', App\Http\Controllers\Api\TaxRateController::class);
    Route::get('/tax-rates/active/dropdown', [App\Http\Controllers\Api\TaxRateController::class, 'getActiveTaxRates']);
   

    // Quotes management
    Route::apiResource('quotes', App\Http\Controllers\Api\QuoteController::class);
    Route::patch('/quotes/{quote}/status', [App\Http\Controllers\Api\QuoteController::class, 'updateStatus']);
    Route::get('/quotes/statistics', [App\Http\Controllers\Api\QuoteController::class, 'statistics']);
    Route::get('/quotes/sorting-options', [App\Http\Controllers\Api\QuoteController::class, 'getSortingOptions']);
    Route::post('/quotes/{quote}/convert-to-invoice', [App\Http\Controllers\Api\QuoteController::class, 'convertToInvoice']);

    // Quote attachments removed - use CommonAttachmentService instead

    // Quote status history
    Route::get('/quotes/{quote}/status-history', [App\Http\Controllers\Api\QuoteController::class, 'getStatusHistory']);

    // Quote versioning routes
    Route::get('/quotes/{quote}/versions', [App\Http\Controllers\Api\QuoteController::class, 'getVersionHistory']);
    Route::get('/quotes/{quote}/versions/{versionNumber}', [App\Http\Controllers\Api\QuoteController::class, 'getVersion']);
    Route::post('/quotes/{quote}/versions/compare', [App\Http\Controllers\Api\QuoteController::class, 'compareVersions']);
    Route::post('/quotes/{quote}/versions/restore', [App\Http\Controllers\Api\QuoteController::class, 'restoreToVersion']);
    Route::get('/quotes/{quote}/versions/statistics', [App\Http\Controllers\Api\QuoteController::class, 'getVersionStatistics']);

    // Debug route for authentication testing
    Route::get('/quotes/test-auth', [App\Http\Controllers\Api\QuoteController::class, 'testAuth']);

    // Quote attachment routes
    Route::post('/quotes/{quote}/attachments', [App\Http\Controllers\Api\QuoteController::class, 'uploadAttachments']);
    Route::get('/quotes/{quote}/attachments', [App\Http\Controllers\Api\QuoteController::class, 'getAttachments']);
    Route::delete('/quotes/{quote}/attachments', [App\Http\Controllers\Api\QuoteController::class, 'deleteAttachment']);
        
    
    // Status history management
    Route::prefix('status-history')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\StatusHistoryController::class, 'index']);
        Route::get('/quotes/{quoteId}', [App\Http\Controllers\Api\StatusHistoryController::class, 'getQuoteHistory']);
        Route::get('/invoices/{invoiceId}', [App\Http\Controllers\Api\StatusHistoryController::class, 'getInvoiceHistory']);
        Route::get('/statistics', [App\Http\Controllers\Api\StatusHistoryController::class, 'statistics']);
    });

    // Payment terms management
    Route::apiResource('payment-terms', App\Http\Controllers\Api\PaymentTermController::class);
    Route::get('/payment-terms/active/dropdown', [App\Http\Controllers\Api\PaymentTermController::class, 'getActivePaymentTerms']);
    
    // Dropdown data routes
    Route::prefix('dropdowns')->group(function () {
        Route::get('/contacts-by-company/{companyId}', [App\Http\Controllers\Api\DropdownController::class, 'getContactsByCompany']);
        Route::get('/contact-details/{contactId}', [App\Http\Controllers\Api\DropdownController::class, 'getContactDetails']);
        Route::get('/invoice-dropdowns', [App\Http\Controllers\Api\DropdownController::class, 'getInvoiceDropdowns']);
        Route::get('/essential-dropdowns', [App\Http\Controllers\Api\DropdownController::class, 'getEssentialDropdowns']);
        Route::get('/company-dropdowns', [App\Http\Controllers\Api\DropdownController::class, 'getCompanyDropdowns']);
        Route::get('/tax-rate-dropdowns', [App\Http\Controllers\Api\DropdownController::class, 'getTaxRateDropdowns']);
        Route::get('/payment-term-dropdowns', [App\Http\Controllers\Api\DropdownController::class, 'getPaymentTermDropdowns']);
        Route::get('/item-name-dropdowns', [App\Http\Controllers\Api\DropdownController::class, 'getItemNameDropdowns']);
    });


    
    // Employment Information APIs
    Route::prefix('employment-insert')->group(function () {
        Route::post('/multiple', [EmploymentInformationInsertController::class, 'insertMultiple']);
        Route::post('/bulk', [EmploymentInformationInsertController::class, 'bulkInsert']);
        Route::post('/superannuation', [EmploymentInformationInsertController::class, 'updateSuperannuation']);
        Route::post('/authorized-fields', [EmploymentInformationInsertController::class, 'updateAuthorizedFields']);
        Route::get('/statistics', [EmploymentInformationInsertController::class, 'getStatistics']);
        Route::get('/employees-by-group', [EmploymentInformationInsertController::class, 'getEmployeesByGroup']);
    });

    // Endpoints to create/update and fetch single employment information
    Route::post('/update-employee-contract-information', [EmployeeController::class, 'updateEmployeeContractInformation']);
    Route::get('/update-employee-contract-information/{employee_id}', [EmployeeController::class, 'getEmployeeContractInformation']);
    
    // Tax Details Management APIs
    Route::prefix('tax-details')->group(function () {
        Route::post('/', [App\Http\Controllers\Api\Employees\TaxController::class, 'store']);                    // Create tax details
        Route::get('/{employee_id}', [App\Http\Controllers\Api\Employees\TaxController::class, 'show']);         // View tax details
        Route::put('/{employee_id}', [App\Http\Controllers\Api\Employees\TaxController::class, 'update']);       // Update tax details
    });
    
    // Bank Account Management APIs
    Route::prefix('bank-accounts')->group(function () {
        Route::post('/', [BankAccountController::class, 'store']);                         // Create bank account
        Route::get('/{employee_id}', [BankAccountController::class, 'show']);             // View bank accounts for employee
        Route::put('/{bank_account_id}', [BankAccountController::class, 'update']);       // Update bank account
    });
    
    // Pay Template Management APIs
    Route::prefix('pay-templates')->group(function () {
        Route::post('/', [App\Http\Controllers\Api\Employees\PayTemplateController::class, 'store']);                            // Create pay template
        Route::get('/{pay_template_id}', [App\Http\Controllers\Api\Employees\PayTemplateController::class, 'show']);             // View specific pay template
        Route::put('/{pay_template_id}', [App\Http\Controllers\Api\Employees\PayTemplateController::class, 'update']);           // Update pay template
    });
    
    // Deduction Management APIs
    Route::prefix('deductions')->group(function () {
        Route::post('/', [DeductionController::class, 'store']);                         // Create deduction
        Route::get('/{employee_id}', [DeductionController::class, 'show']);             // View deductions for employee
        Route::put('/{deduction_id}', [DeductionController::class, 'update']);          // Update deduction
    });

    // Leave Requests
    Route::get('/leave-requests', [EmployeeLeaveController::class, 'index']);
    Route::get('/leave-requests/{leaveRequestId}', [EmployeeLeaveController::class, 'showLeave']);
    Route::post('/leave-requests', [EmployeeLeaveController::class, 'store']);
    Route::post('/leave-requests/{leaveRequestId}/approve', [EmployeeLeaveController::class, 'approveLeave']);
    Route::post('/leave-requests/{leaveRequestId}/reject', [EmployeeLeaveController::class, 'rejectLeave']);

    // Assign Leave Type Management
    Route::post('/assign-leave-type', [EmployeeLeaveController::class, 'assignLeaveType']);
    Route::get('/assign-leave-type/{id}', [EmployeeLeaveController::class, 'showAssignLeaveType']);
    Route::get('/employees/{employeeId}/assign-leave-types', [EmployeeLeaveController::class, 'getEmployeeAssignLeaveTypes']);
    Route::put('/assign-leave-type/{id}', [EmployeeLeaveController::class, 'updateAssignLeaveType']);
    Route::delete('/assign-leave-type/{id}', [EmployeeLeaveController::class, 'deleteAssignLeaveType']);
    Route::get('/leave-type-dropdowns', [EmployeeLeaveController::class, 'getLeaveTypeDropdowns']);
    Route::get('/employees/{employeeId}/available-leave-types', [EmployeeLeaveController::class, 'getAvailableLeaveTypesForEmployee']);
    Route::get('/employees/{employeeId}/leave-balances', [EmployeeLeaveController::class, 'getEmployeeLeaveBalances']);
    Route::get('/employees/{employeeId}/leave-types/{leaveTypeId}/pay-period-hours', [EmployeeLeaveController::class, 'getPayPeriodHours']);

    // Accounts Management
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::get('/accounts/{accountId}', [AccountController::class, 'getAccountById']);
    
    // Account Dropdown APIs
    Route::get('/account-dropdown', [AccountController::class, 'getDropdowns']);
    Route::get('/account-dropdown/{accountType}/detail-types', [AccountController::class, 'getDetailTypes']);
    Route::get('/account-details', [AccountController::class, 'getAccountDetails']);
    
    // Supplier Management APIs
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers', [SupplierController::class, 'index']);
Route::get('/suppliers/{supplierId}', [SupplierController::class, 'show']);
Route::get('/supplier-dropdown', [SupplierController::class, 'getDropdowns']);
});


