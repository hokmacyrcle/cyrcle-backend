<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Supplier;
use App\Models\Currency;
use App\Models\PaymentTerm;
use App\Models\ExpenseCategory;
use App\Services\CommonAttachmentService;
use App\Models\Attachment;

class SupplierController extends Controller
{
    /**
     * Store a new supplier
     */
    public function store(Request $request)
    {
        // Get the authenticated user's company context
        $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
        if (!$user || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found. Please select a company first.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'title' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'supplier_display_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'supplier_display_name')->where('company_id', $user->company_id)
            ],
            'company_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('suppliers', 'company_name')->where('company_id', $user->company_id)
            ],
            'email' => 'nullable|email|max:255',
            'phone_no' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'phone_no')->where('company_id', $user->company_id)
            ],
            'mobile_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'mobile_number')->where('company_id', $user->company_id)
            ],
            'fax' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'fax')->where('company_id', $user->company_id)
            ],
            'other' => 'nullable|string|max:255',
            'website' => [
                'nullable',
                'url',
                'max:255',
                Rule::unique('suppliers', 'website')->where('company_id', $user->company_id)
            ],
            'street_address1' => 'nullable|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city_state' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'taxes_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('suppliers', 'taxes_number')->where('company_id', $user->company_id)
            ],
            'expense_rates_billing_rate' => 'nullable|numeric|min:0',
            'payment_terms_id' => 'nullable|integer|exists:payment_terms,id',
            'account_no' => 'nullable|string|max:50',
            'create_batch_payments' => 'required|in:Yes,No',
            'bsb' => 'nullable|string|max:20',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('suppliers', 'bank_account_number')->where('company_id', $user->company_id)
            ],
            'statement_text' => 'nullable|string|max:255',
            'default_expense_cat_id' => 'nullable|integer|exists:expense_categories,id',
            'opening_balance' => 'nullable|numeric',
            'opening_balance_date' => 'nullable|date',
            'attachments.*' => 'nullable|file|max:20480', // 20MB max per file
        ], [
            'create_batch_payments.in' => 'The create batch payments field must be either Yes or No.',
            'create_batch_payments.required' => 'The create batch payments field is required. Please select Yes or No.',
            'supplier_display_name.required' => 'The supplier display name field is required.',
            'supplier_display_name.unique' => 'The supplier display name already exists for this company.',
            'first_name.required' => 'The first name field is required.',
            'last_name.required' => 'The last name field is required.',
            'company_name.unique' => 'The company name already exists for this company.',
            'phone_no.unique' => 'The phone number already exists for this company.',
            'mobile_number.unique' => 'The mobile number already exists for this company.',
            'fax.unique' => 'The fax number already exists for this company.',
            'website.unique' => 'The website URL already exists for this company.',
            'taxes_number.unique' => 'The tax number already exists for this company.',
            'bank_account_number.unique' => 'The bank account number already exists for this company.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prepare supplier data with automatic company_id
        $supplierData = $validator->validated();
        $supplierData['company_id'] = $user->company_id;
        $supplierData['created_by'] = $user->id;

        $supplier = Supplier::create($supplierData);

        // Handle attachments if provided
        $uploadedAttachments = [];
        if ($request->hasFile('attachments')) {
            $attachmentService = new CommonAttachmentService();
            $uploadedAttachments = $attachmentService->storeMultipleAttachments(
                'Supplier', 
                $supplier->company_id, 
                $supplier->supplier_id, 
                $request->file('attachments')
            );
        }

        // Get related data for response
        $currency = $supplier->currency;
        $paymentTerms = $supplier->paymentTerms;
        $defaultExpenseCategory = $supplier->defaultExpenseCategory;

        $response = [
            'supplier' => [
                'supplier_id' => $supplier->supplier_id,
                'supplier_display_name' => $supplier->supplier_display_name,
                'first_name' => $supplier->first_name,
                'last_name' => $supplier->last_name,
                'company_name' => $supplier->company_name,
                'email' => $supplier->email,
                'phone_no' => $supplier->phone_no,
                'mobile_number' => $supplier->mobile_number,
                'create_batch_payments' => $supplier->create_batch_payments,
                'company_id' => $supplier->company_id,
                'created_at' => $supplier->created_at,
                'updated_at' => $supplier->updated_at
            ],
            'currency' => $currency ? [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol
            ] : null,
            'payment_terms' => $paymentTerms ? [
                'id' => $paymentTerms->id,
                'name' => $paymentTerms->name,
                'days' => $paymentTerms->fixed_days_value
            ] : null,
            'default_expense_category' => $defaultExpenseCategory ? [
                'id' => $defaultExpenseCategory->id,
                'name' => $defaultExpenseCategory->name
            ] : null,
            'attachments' => $uploadedAttachments
        ];

        return response()->json([
            'success' => true,
            'message' => 'Supplier created successfully',
            'data' => $response,
        ], 201);
    }

    /**
     * Get supplier dropdown options
     */
    public function getDropdowns(Request $request)
    {
        try {
            // Get currencies
            $currencies = Currency::active()
                ->select('id', 'code', 'name', 'symbol')
                ->orderBy('name')
                ->get();

            // Get payment terms
            $paymentTerms = PaymentTerm::select('id', 'name', 'fixed_days_value', 'due_calculation_method')
                ->orderBy('name')
                ->get();

            // Get expense categories for the user's company
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            $expenseCategories = [];
            
            if ($user && $user->company_id) {
                $expenseCategories = ExpenseCategory::active()
                    ->where('company_id', $user->company_id)
                    ->select('id', 'name', 'code', 'description')
                    ->orderBy('name')
                    ->get();
            } else {
                // If no user or company_id, get all expense categories
                $expenseCategories = ExpenseCategory::active()
                    ->select('id', 'name', 'code', 'description', 'company_id')
                    ->orderBy('name')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'currencies' => $currencies,
                    'payment_terms' => $paymentTerms,
                    'expense_categories' => $expenseCategories,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dropdown data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get suppliers list
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $suppliers = Supplier::where('company_id', $user->company_id)
                ->with(['currency', 'paymentTerms', 'defaultExpenseCategory'])
                ->select('supplier_id', 'company_name', 'company_id', 'currency_id', 'payment_terms_id', 'default_expense_cat_id')
                ->orderBy('company_name')
                ->get();

            // Transform the data to match the requested format
            $formattedSuppliers = $suppliers->map(function ($supplier) {
                return [
                    'company_name' => $supplier->company_name,
                    'company_id' => $supplier->company_id,
                    'default_expense_category' => $supplier->defaultExpenseCategory ? [
                        'id' => $supplier->defaultExpenseCategory->id,
                        'name' => $supplier->defaultExpenseCategory->name
                    ] : null,
                    'payment_terms' => $supplier->paymentTerms ? [
                        'id' => $supplier->paymentTerms->id,
                        'name' => $supplier->paymentTerms->name
                    ] : null,
                    'currency' => $supplier->currency ? [
                        'id' => $supplier->currency->id,
                        'code' => $supplier->currency->code,
                        'name' => $supplier->currency->name,
                        'symbol' => $supplier->currency->symbol
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedSuppliers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier by ID
     */
    public function show(Request $request, $supplierId)
    {
        try {
            $supplier = Supplier::with(['currency', 'paymentTerms', 'defaultExpenseCategory'])
                ->find($supplierId);
            
            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier not found'
                ], 404);
            }

            // Get attachments for the supplier
            $attachmentService = new CommonAttachmentService();
            $attachments = $attachmentService->getAttachments('Supplier', $supplier->supplier_id);

            $response = [
                'supplier' => $supplier,
                'currency' => $supplier->currency ? [
                    'id' => $supplier->currency->id,
                    'code' => $supplier->currency->code,
                    'name' => $supplier->currency->name,
                    'symbol' => $supplier->currency->symbol
                ] : null,
                'payment_terms' => $supplier->paymentTerms ? [
                    'id' => $supplier->paymentTerms->id,
                    'name' => $supplier->paymentTerms->name,
                    'days' => $supplier->paymentTerms->fixed_days_value
                ] : null,
                'default_expense_category' => $supplier->defaultExpenseCategory ? [
                    'id' => $supplier->defaultExpenseCategory->id,
                    'name' => $supplier->defaultExpenseCategory->name
                ] : null,
                'attachments' => $attachments
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supplier details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
