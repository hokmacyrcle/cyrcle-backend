<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AccountController;
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

Route::get('/ping', function () {
    return response()->json([
        'status' => 'pong',
        'message' => 'API is responding',
        'server_time' => now()->toISOString()
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy'
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

    // Company routes
    Route::apiResource('companies', App\Http\Controllers\Api\CompanyController::class);
    Route::patch('/companies/{company}/toggle-status', [App\Http\Controllers\Api\CompanyController::class, 'toggleStatus']);
    
    // Company attachments removed - use CommonAttachmentService instead
    
    // Bill attachments
    Route::post('/bills/{id}/attachments', [App\Http\Controllers\Api\BillController::class, 'uploadAttachments']);
    Route::get('/bills/{id}/attachments', [App\Http\Controllers\Api\BillController::class, 'getAttachments']);
    Route::delete('/bills/{id}/attachments', [App\Http\Controllers\Api\BillController::class, 'deleteAttachment']);
    Route::get('/bills/{id}/attachments/{filename}/download', [App\Http\Controllers\Api\BillController::class, 'downloadAttachment']);

    // Sales (Australian invoicing): the screens in Sales use these. Everything is limited to the selected business.
    // Payroll: employees of the business being worked in
    Route::prefix('payroll')->group(function () {
        Route::get('/employees', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'index']);
        Route::post('/employees', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'show'])->whereNumber('id');
        Route::put('/employees/{id}', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'update'])->whereNumber('id');
        Route::post('/employees/{id}/archive', [App\Http\Controllers\Api\Payroll\EmployeeController::class, 'setArchived'])->whereNumber('id');
        Route::post('/pay-runs/{id}/lines/{lineId}/items', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'addItem'])->whereNumber('id')->whereNumber('lineId');
        Route::delete('/pay-runs/{id}/lines/{lineId}/items/{itemId}', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'removeItem'])->whereNumber('id')->whereNumber('lineId')->whereNumber('itemId');
        Route::post('/pay-runs/{id}/lines/{lineId}/leave', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'addLeave'])->whereNumber('id')->whereNumber('lineId');
        Route::get('/leave', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'index']);
        Route::put('/leave/types/{id}', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'updateType'])->whereNumber('id');
        Route::get('/leave/requests', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'requests']);
        Route::post('/leave/requests', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'storeRequest']);
        Route::post('/leave/requests/{id}/approve', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'approve'])->whereNumber('id');
        Route::post('/leave/requests/{id}/decline', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'decline'])->whereNumber('id');
        Route::delete('/leave/requests/{id}', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'destroy'])->whereNumber('id');
        Route::post('/leave/adjustments', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'adjust']);
        Route::get('/leave/employees/{employeeId}/ledger', [App\Http\Controllers\Api\Payroll\LeaveController::class, 'ledger'])->whereNumber('employeeId');
        Route::get('/timesheets', [App\Http\Controllers\Api\Payroll\TimesheetController::class, 'index']);
        Route::put('/timesheets/{employeeId}', [App\Http\Controllers\Api\Payroll\TimesheetController::class, 'save'])->whereNumber('employeeId');
        Route::post('/timesheets/{employeeId}/approve', [App\Http\Controllers\Api\Payroll\TimesheetController::class, 'approve'])->whereNumber('employeeId');
        Route::post('/pay-runs/{id}/refresh-hours', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'refreshHours'])->whereNumber('id');
        Route::get('/pay-runs/{id}/bank-file', [App\Http\Controllers\Api\Payroll\BankFileController::class, 'show'])->whereNumber('id');
        Route::get('/pay-runs/{id}/stp', [App\Http\Controllers\Api\Payroll\StpController::class, 'show'])->whereNumber('id');
        Route::get('/overview', [App\Http\Controllers\Api\Payroll\PayrollOverviewController::class, 'show']);
        Route::post('/pay-runs/{id}/super-paid', [App\Http\Controllers\Api\Payroll\PayRunController::class, 'superPaid'])->whereNumber('id');
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

    Route::prefix('banking')->group(function () {
        $acct = App\Http\Controllers\Api\Banking\BankAccountController::class;
        $imp = App\Http\Controllers\Api\Banking\BankImportController::class;
        $txn = App\Http\Controllers\Api\Banking\BankTransactionController::class;
        Route::get('/accounts', [$acct, 'index']);
        Route::post('/accounts', [$acct, 'store']);
        Route::put('/accounts/{id}', [$acct, 'update'])->whereNumber('id');
        Route::post('/accounts/{id}/archive', [$acct, 'archive'])->whereNumber('id');
        Route::post('/accounts/{id}/import/preview', [$imp, 'preview'])->whereNumber('id');
        Route::post('/accounts/{id}/import', [$imp, 'store'])->whereNumber('id');
        Route::delete('/imports/{id}', [$imp, 'undo'])->whereNumber('id');
        Route::get('/accounts/{id}/transactions', [$txn, 'index'])->whereNumber('id');
        Route::get('/transactions', [$txn, 'all']);
        Route::get('/transactions/export', [$txn, 'export']);
        Route::get('/summary', [$txn, 'summary']);

        $feed = App\Http\Controllers\Api\Banking\BankFeedController::class;
        Route::get('/feed/status', [$feed, 'status']);
        Route::get('/feed/institutions', [$feed, 'institutions']);
        Route::post('/feed/start', [$feed, 'start']);
        Route::get('/feed/links/{id}/accounts', [$feed, 'accounts'])->whereNumber('id');
        Route::post('/feed/links/{id}/connect', [$feed, 'connect'])->whereNumber('id');
        Route::delete('/feed/links/{id}', [$feed, 'disconnect'])->whereNumber('id');
        Route::post('/feed/accounts/{id}/sync', [$feed, 'sync'])->whereNumber('id');
        Route::post('/transactions/{id}/status', [$txn, 'setStatus'])->whereNumber('id');
        Route::post('/transactions/bulk-status', [$txn, 'bulkStatus']);

        $rec = App\Http\Controllers\Api\Banking\ReconcileController::class;
        Route::get('/reconcile/{accountId}', [$rec, 'start'])->whereNumber('accountId');
        Route::get('/reconcile/{accountId}/lines', [$rec, 'lines'])->whereNumber('accountId');
        Route::post('/reconcile/{accountId}', [$rec, 'finish'])->whereNumber('accountId');
        Route::delete('/reconcile/{id}', [$rec, 'undo'])->whereNumber('id');

        $chart = App\Http\Controllers\Api\Banking\ChartAccountController::class;
        Route::get('/chart-accounts', [$chart, 'index']);
        Route::post('/chart-accounts', [$chart, 'store']);
        Route::put('/chart-accounts/{id}', [$chart, 'update'])->whereNumber('id');
        Route::post('/chart-accounts/{id}/archive', [$chart, 'archive'])->whereNumber('id');

        $rcp = App\Http\Controllers\Api\Banking\BankReceiptController::class;
        Route::get('/receipts', [$rcp, 'index']);
        Route::post('/receipts', [$rcp, 'store']);
        Route::get('/receipts/{id}/file', [$rcp, 'view'])->whereNumber('id');
        Route::put('/receipts/{id}', [$rcp, 'update'])->whereNumber('id');
        Route::delete('/receipts/{id}', [$rcp, 'destroy'])->whereNumber('id');
    });

    Route::prefix('sales')->group(function () {
        Route::get('/firm/overdue', [App\Http\Controllers\Api\Sales\FirmOverdueController::class, 'show']);
        Route::get('/summary', [App\Http\Controllers\Api\Sales\SummaryController::class, 'show']);
        Route::get('/business', [App\Http\Controllers\Api\Sales\SummaryController::class, 'businessInfo']);

        Route::get('/invoices', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'index']);
        Route::post('/invoices', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'store']);
        Route::get('/invoices/{id}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'show'])->whereNumber('id');
        Route::put('/invoices/{id}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'update'])->whereNumber('id');
        Route::delete('/invoices/{id}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'destroy'])->whereNumber('id');
        Route::get('/invoices/{id}/reminder', [App\Http\Controllers\Api\Sales\InvoiceReminderController::class, 'show'])->whereNumber('id');
        Route::post('/invoices/{id}/reminders', [App\Http\Controllers\Api\Sales\InvoiceReminderController::class, 'store'])->whereNumber('id');
        Route::post('/invoices/{id}/issue', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'issue'])->whereNumber('id');
        Route::post('/invoices/{id}/void', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'void'])->whereNumber('id');
        Route::post('/invoices/{id}/payments', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'addPayment'])->whereNumber('id');
        Route::delete('/invoices/{id}/payments/{transactionId}', [App\Http\Controllers\Api\Sales\InvoiceController::class, 'removePayment'])->whereNumber('id')->whereNumber('transactionId');

        Route::get('/recurring-invoices', [App\Http\Controllers\Api\Sales\RecurringInvoiceController::class, 'index']);
        Route::post('/recurring-invoices', [App\Http\Controllers\Api\Sales\RecurringInvoiceController::class, 'store']);
        Route::put('/recurring-invoices/{id}', [App\Http\Controllers\Api\Sales\RecurringInvoiceController::class, 'update'])->whereNumber('id');
        Route::delete('/recurring-invoices/{id}', [App\Http\Controllers\Api\Sales\RecurringInvoiceController::class, 'destroy'])->whereNumber('id');

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


