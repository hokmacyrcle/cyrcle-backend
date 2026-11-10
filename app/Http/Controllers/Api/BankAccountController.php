<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class BankAccountController extends Controller
{
    /**
     * Store new bank account(s) for an employee
     * Accepts both single object and array of bank accounts
     */
    public function store(Request $request): JsonResponse
    {
        // Debug: Log the request structure
        \Log::info('Bank Account Request Debug', [
            'isJson' => $request->isJson(),
            'contentType' => $request->header('Content-Type'),
            'has_company_id' => $request->has('company_id'),
            'company_id_type' => $request->has('company_id') ? gettype($request->company_id) : 'not_set',
            'json_all' => $request->isJson() ? $request->json()->all() : 'not_json',
            'all_data' => $request->all(),
            'has_success' => $request->has('success'),
            'has_message' => $request->has('message'),
            'has_data' => $request->has('data')
        ]);
        
        // Check if data is wrapped in a response structure (success, message, data)
        $isWrappedInResponse = $request->has('success') || $request->has('message') || $request->has('data');
        
        if ($isWrappedInResponse) {
            // Extract data from wrapped response structure
            return $this->storeWrapped($request);
        }
        
        // Check if request has nested structure under company_id
        $hasNestedStructure = $request->has('company_id') && is_array($request->company_id);
        
        if ($hasNestedStructure) {
            // Handle nested structure under company_id
            return $this->storeNested($request);
        }
        
        // Check if request contains array of bank accounts
        $isArray = $request->isJson() && is_array($request->json()->all());
        
        // Also check if the request has array-like structure in form data
        $isFormArray = !$request->isJson() && $request->has('0') && is_array($request->input('0'));
        
        if ($isArray || $isFormArray) {
            // Handle multiple bank accounts
            return $this->storeMultiple($request);
        } else {
            // Handle single bank account
            return $this->storeSingle($request);
        }
    }

    /**
     * Store bank account from wrapped response structure
     */
    private function storeWrapped(Request $request): JsonResponse
    {
        // Extract the actual data from the wrapped structure
        $wrappedData = $request->all();
        
        // Try to find the actual bank account data
        $bankAccounts = null;
        
        if (isset($wrappedData['data']) && is_array($wrappedData['data'])) {
            $bankAccounts = $wrappedData['data'];
        } elseif (isset($wrappedData['company_id']) && is_array($wrappedData['company_id'])) {
            $bankAccounts = $wrappedData['company_id'];
        } else {
            // If no clear data structure, try to find array-like data
            foreach ($wrappedData as $key => $value) {
                if (is_array($value) && isset($value[0]) && is_array($value[0])) {
                    $bankAccounts = $value;
                    break;
                }
            }
        }
        
        if (!$bankAccounts) {
            return response()->json([
                'success' => false,
                'message' => 'Could not extract bank account data from wrapped request structure',
                'debug' => $wrappedData
            ], 422);
        }
        
        // Check if it's an array of bank accounts or a single account
        if (isset($bankAccounts[0]) && is_array($bankAccounts[0])) {
            // Multiple bank accounts
            return $this->storeMultipleFromData($bankAccounts);
        } else {
            // Single bank account
            return $this->storeSingleFromData($bankAccounts);
        }
    }

    /**
     * Store single bank account from extracted data
     */
    private function storeSingleFromData(array $accountData): JsonResponse
    {
        $validator = Validator::make($accountData, [
            'employee_id' => 'required|exists:employees,emp_id',
            'statement_text' => 'nullable|string|max:255',
            'account_name' => 'required|string|max:255',
            'bsb_number' => 'nullable|string|max:10',
            'account_number' => 'required|string|max:50|unique:emp_bank_accounts,account_number',
            'balance' => 'nullable|numeric|min:0|max:999999999.99',
            'is_primary_account' => 'boolean',
            'status' => 'nullable|in:active,inactive',
        ], [
            'account_number.unique' => 'The account number already exists. Please use a different account number.',
            'account_number.required' => 'The account number field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $accountData['employee_id'])
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Set default values
            $data = $accountData;
            $data['balance'] = $data['balance'] ?? 0.00;
            $data['status'] = $data['status'] ?? 'active';
            $data['is_primary_account'] = $data['is_primary_account'] ?? false;
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            // If this is set as primary, remove primary status from other accounts first
            if ($data['is_primary_account']) {
                BankAccount::where('employee_id', $accountData['employee_id'])
                    ->update(['is_primary_account' => false]);
            }

            $bankAccount = BankAccount::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Bank account created successfully',
                'data' => $bankAccount,
            ], 201);

        } catch (\Exception $e) {
            // Handle unique constraint violation specifically
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unique_primary_account_per_employee')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This employee already has a primary bank account. Only one primary account is allowed per employee.',
                    'error' => 'Primary account constraint violation'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store multiple bank accounts from extracted data
     */
    private function storeMultipleFromData(array $bankAccounts): JsonResponse
    {
        // Validate each bank account
        $validator = Validator::make($bankAccounts, [
            '*.employee_id' => 'required|exists:employees,emp_id',
            '*.statement_text' => 'nullable|string|max:255',
            '*.account_name' => 'required|string|max:255',
            '*.bsb_number' => 'nullable|string|max:10',
            '*.account_number' => 'required|string|max:50|unique:emp_bank_accounts,account_number',
            '*.balance' => 'nullable|numeric|min:0|max:999999999.99',
            '*.is_primary_account' => 'boolean',
            '*.status' => 'nullable|in:active,inactive',
        ], [
            '*.account_number.unique' => 'The account number already exists. Please use a different account number.',
            '*.account_number.required' => 'The account number field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $createdAccounts = [];
            $employeeIds = collect($bankAccounts)->pluck('employee_id')->unique();

            // Validate all employees belong to user's company
            foreach ($employeeIds as $employeeId) {
                $employee = Employee::where('emp_company_id', $user->company_id)
                    ->where('emp_id', $employeeId)
                    ->first();

                if (!$employee) {
                    return response()->json([
                        'success' => false,
                        'message' => "Employee with ID {$employeeId} not found or access denied",
                    ], 404);
                }
            }

            // Process each bank account
            foreach ($bankAccounts as $accountData) {
                // Set default values
                $accountData['balance'] = $accountData['balance'] ?? 0.00;
                $accountData['status'] = $accountData['status'] ?? 'active';
                $accountData['is_primary_account'] = $accountData['is_primary_account'] ?? false;
                $accountData['created_by'] = $user->id;
                $accountData['updated_by'] = $user->id;

                // If this is set as primary, remove primary status from other accounts first
                if ($accountData['is_primary_account']) {
                    BankAccount::where('employee_id', $accountData['employee_id'])
                        ->update(['is_primary_account' => false]);
                }

                $bankAccount = BankAccount::create($accountData);
                $createdAccounts[] = $bankAccount;
            }

            return response()->json([
                'success' => true,
                'message' => count($createdAccounts) . ' bank account(s) created successfully',
                'data' => $createdAccounts,
            ], 201);

        } catch (\Exception $e) {
            // Handle unique constraint violation specifically
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unique_primary_account_per_employee')) {
                return response()->json([
                    'success' => false,
                    'message' => 'One of the employees already has a primary bank account. Only one primary account is allowed per employee.',
                    'error' => 'Primary account constraint violation'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank accounts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store single bank account
     */
    private function storeSingle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'statement_text' => 'nullable|string|max:255',
            'account_name' => 'required|string|max:255',
            'bsb_number' => 'nullable|string|max:10',
            'account_number' => 'required|string|max:50|unique:emp_bank_accounts,account_number',
            'balance' => 'nullable|numeric|min:0|max:999999999.99',
            'is_primary_account' => 'boolean',
            'status' => 'nullable|in:active,inactive',
        ], [
            'account_number.unique' => 'The account number already exists. Please use a different account number.',
            'account_number.required' => 'The account number field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $request->employee_id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Set default values
            $data = $request->all();
            $data['balance'] = $data['balance'] ?? 0.00;
            $data['status'] = $data['status'] ?? 'active';
            $data['is_primary_account'] = $data['is_primary_account'] ?? false;
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            // If this is set as primary, remove primary status from other accounts first
            if ($data['is_primary_account']) {
                BankAccount::where('employee_id', $request->employee_id)
                    ->update(['is_primary_account' => false]);
            }

            $bankAccount = BankAccount::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Bank account created successfully',
                'data' => $bankAccount,
            ], 201);

        } catch (\Exception $e) {
            // Handle unique constraint violation specifically
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unique_primary_account_per_employee')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This employee already has a primary bank account. Only one primary account is allowed per employee.',
                    'error' => 'Primary account constraint violation'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store bank account with nested structure under company_id
     */
    private function storeNested(Request $request): JsonResponse
    {
        // Extract the nested data from company_id
        $nestedData = $request->company_id;
        
        // Check if it's an array of bank accounts or a single account
        if (isset($nestedData[0]) && is_array($nestedData[0])) {
            // Multiple bank accounts nested under company_id
            return $this->storeMultipleNested($request);
        } else {
            // Single bank account nested under company_id
            return $this->storeSingleNested($request);
        }
    }

    /**
     * Store single bank account from nested structure
     */
    private function storeSingleNested(Request $request): JsonResponse
    {
        $nestedData = $request->company_id;
        
        $validator = Validator::make($nestedData, [
            'employee_id' => 'required|exists:employees,emp_id',
            'statement_text' => 'nullable|string|max:255',
            'account_name' => 'required|string|max:255',
            'bsb_number' => 'nullable|string|max:10',
            'account_number' => 'required|string|max:50|unique:emp_bank_accounts,account_number',
            'balance' => 'nullable|numeric|min:0|max:999999999.99',
            'is_primary_account' => 'boolean',
            'status' => 'nullable|in:active,inactive',
        ], [
            'account_number.unique' => 'The account number already exists. Please use a different account number.',
            'account_number.required' => 'The account number field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $nestedData['employee_id'])
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Set default values
            $data = $nestedData;
            $data['balance'] = $data['balance'] ?? 0.00;
            $data['status'] = $data['status'] ?? 'active';
            $data['is_primary_account'] = $data['is_primary_account'] ?? false;
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            // If this is set as primary, remove primary status from other accounts first
            if ($data['is_primary_account']) {
                BankAccount::where('employee_id', $nestedData['employee_id'])
                    ->update(['is_primary_account' => false]);
            }

            $bankAccount = BankAccount::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Bank account created successfully',
                'data' => $bankAccount,
            ], 201);

        } catch (\Exception $e) {
            // Handle unique constraint violation specifically
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unique_primary_account_per_employee')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This employee already has a primary bank account. Only one primary account is allowed per employee.',
                    'error' => 'Primary account constraint violation'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store multiple bank accounts from nested structure
     */
    private function storeMultipleNested(Request $request): JsonResponse
    {
        $bankAccounts = $request->company_id;
        
        // Validate that we have an array
        if (!is_array($bankAccounts)) {
            return response()->json([
                'success' => false,
                'message' => 'Request must contain an array of bank accounts under company_id',
            ], 422);
        }

        // Validate each bank account
        $validator = Validator::make($bankAccounts, [
            '*.employee_id' => 'required|exists:employees,emp_id',
            '*.statement_text' => 'nullable|string|max:255',
            '*.account_name' => 'required|string|max:255',
            '*.bsb_number' => 'nullable|string|max:10',
            '*.account_number' => 'required|string|max:50|unique:emp_bank_accounts,account_number',
            '*.balance' => 'nullable|numeric|min:0|max:999999999.99',
            '*.is_primary_account' => 'boolean',
            '*.status' => 'nullable|in:active,inactive',
        ], [
            '*.account_number.unique' => 'The account number already exists. Please use a different account number.',
            '*.account_number.required' => 'The account number field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $createdAccounts = [];
            $employeeIds = collect($bankAccounts)->pluck('employee_id')->unique();

            // Validate all employees belong to user's company
            foreach ($employeeIds as $employeeId) {
                $employee = Employee::where('emp_company_id', $user->company_id)
                    ->where('emp_id', $employeeId)
                    ->first();

                if (!$employee) {
                    return response()->json([
                        'success' => false,
                        'message' => "Employee with ID {$employeeId} not found or access denied",
                    ], 404);
                }
            }

            // Process each bank account
            foreach ($bankAccounts as $accountData) {
                // Set default values
                $accountData['balance'] = $accountData['balance'] ?? 0.00;
                $accountData['status'] = $accountData['status'] ?? 'active';
                $accountData['is_primary_account'] = $accountData['is_primary_account'] ?? false;
                $accountData['created_by'] = $user->id;
                $accountData['updated_by'] = $user->id;

                // If this is set as primary, remove primary status from other accounts first
                if ($accountData['is_primary_account']) {
                    BankAccount::where('employee_id', $accountData['employee_id'])
                        ->update(['is_primary_account' => false]);
                }

                $bankAccount = BankAccount::create($accountData);
                $createdAccounts[] = $bankAccount;
            }

            return response()->json([
                'success' => true,
                'message' => count($createdAccounts) . ' bank account(s) created successfully',
                'data' => $createdAccounts,
            ], 201);

        } catch (\Exception $e) {
            // Handle unique constraint violation specifically
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unique_primary_account_per_employee')) {
                return response()->json([
                    'success' => false,
                    'message' => 'One of the employees already has a primary bank account. Only one primary account is allowed per employee.',
                    'error' => 'Primary account constraint violation'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank accounts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store multiple bank accounts
     */
    private function storeMultiple(Request $request): JsonResponse
    {
        // Handle both JSON and form data
        if ($request->isJson()) {
            $bankAccounts = $request->json()->all();
        } else {
            // Handle form data array
            $bankAccounts = $request->all();
        }
        
        // Validate that we have an array
        if (!is_array($bankAccounts)) {
            return response()->json([
                'success' => false,
                'message' => 'Request must contain an array of bank accounts',
            ], 422);
        }

        // Validate each bank account
        $validator = Validator::make($bankAccounts, [
            '*.employee_id' => 'required|exists:employees,emp_id',
            '*.statement_text' => 'nullable|string|max:255',
            '*.account_name' => 'required|string|max:255',
            '*.bsb_number' => 'nullable|string|max:10',
            '*.account_number' => 'required|string|max:50|unique:emp_bank_accounts,account_number',
            '*.balance' => 'nullable|numeric|min:0|max:999999999.99',
            '*.is_primary_account' => 'boolean',
            '*.status' => 'nullable|in:active,inactive',
        ], [
            '*.account_number.unique' => 'The account number already exists. Please use a different account number.',
            '*.account_number.required' => 'The account number field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $createdAccounts = [];
            $employeeIds = collect($bankAccounts)->pluck('employee_id')->unique();

            // Validate all employees belong to user's company
            foreach ($employeeIds as $employeeId) {
                $employee = Employee::where('emp_company_id', $user->company_id)
                    ->where('emp_id', $employeeId)
                    ->first();

                if (!$employee) {
                    return response()->json([
                        'success' => false,
                        'message' => "Employee with ID {$employeeId} not found or access denied",
                    ], 404);
                }
            }

            // Process each bank account
            foreach ($bankAccounts as $accountData) {
                // Set default values
                $accountData['balance'] = $accountData['balance'] ?? 0.00;
                $accountData['status'] = $accountData['status'] ?? 'active';
                $accountData['is_primary_account'] = $accountData['is_primary_account'] ?? false;
                $accountData['created_by'] = $user->id;
                $accountData['updated_by'] = $user->id;

                // If this is set as primary, remove primary status from other accounts first
                if ($accountData['is_primary_account']) {
                    BankAccount::where('employee_id', $accountData['employee_id'])
                        ->update(['is_primary_account' => false]);
                }

                $bankAccount = BankAccount::create($accountData);
                $createdAccounts[] = $bankAccount;
            }

            return response()->json([
                'success' => true,
                'message' => count($createdAccounts) . ' bank account(s) created successfully',
                'data' => $createdAccounts,
            ], 201);

        } catch (\Exception $e) {
            // Handle unique constraint violation specifically
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unique_primary_account_per_employee')) {
                return response()->json([
                    'success' => false,
                    'message' => 'One of the employees already has a primary bank account. Only one primary account is allowed per employee.',
                    'error' => 'Primary account constraint violation'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank accounts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display bank accounts for a specific employee
     */
    public function show(string $employee_id): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $employee_id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Get bank accounts for this employee
            $bankAccounts = BankAccount::where('employee_id', $employee_id)->get();

            if ($bankAccounts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No bank accounts found for this employee',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank accounts retrieved successfully',
                'data' => $bankAccounts,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank accounts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update bank account for a specific employee
     */
    public function update(Request $request, string $bank_account_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'statement_text' => 'nullable|string|max:255',
            'account_name' => 'required|string|max:255',
            'bsb_number' => 'nullable|string|max:10',
            'account_number' => 'required|string|max:50|unique:emp_bank_accounts,account_number,' . $bank_account_id . ',bank_account_id',
            'balance' => 'nullable|numeric|min:0|max:999999999.99',
            'is_primary_account' => 'boolean',
            'status' => 'nullable|in:active,inactive',
        ], [
            'account_number.unique' => 'The account number already exists. Please use a different account number.',
            'account_number.required' => 'The account number field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Find bank account with employee validation
            $bankAccount = BankAccount::with('employee')->find($bank_account_id);

            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found',
                ], 404);
            }

            // Validate employee belongs to user's company
            if ($bankAccount->employee->emp_company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this bank account',
                ], 403);
            }

            // If this is set as primary, remove primary status from other accounts
            if ($request->is_primary_account) {
                BankAccount::where('employee_id', $bankAccount->employee_id)
                    ->where('bank_account_id', '!=', $bank_account_id)
                    ->update(['is_primary_account' => false]);
            }

            // Update bank account
            $data = $request->all();
            $data['updated_by'] = $user->id;
            
            $bankAccount->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Bank account updated successfully',
                'data' => $bankAccount,
            ]);

        } catch (\Exception $e) {
            // Handle unique constraint violation specifically
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unique_primary_account_per_employee')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This employee already has a primary bank account. Only one primary account is allowed per employee.',
                    'error' => 'Primary account constraint violation'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
