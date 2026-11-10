<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Account;
use App\Models\AccountDropdown;

class AccountController extends Controller
{
    /**
     * Store a new account
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
            'account_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('accounts', 'account_name')->where('company_id', $user->company_id)
            ],
            'account_type' => 'required|integer|exists:account_dropdowns,id',
            'detail_type' => 'required|integer|exists:account_dropdowns,id',
            'subaccount' => 'required|in:Yes,No',
            'parent_account' => [
                'nullable',
                'integer',
                Rule::exists('account_dropdowns', 'id')->where('type', 'parent_account')
            ],
            'description' => 'nullable|string',
        ], [
            'account_name.unique' => 'The account name already exists for this company.',
            'account_name.required' => 'The account name field is required.',
            'subaccount.in' => 'The subaccount field must be either Yes or No.',
            'subaccount.required' => 'The subaccount field is required. Please select Yes or No.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prepare account data with automatic company_id
        $accountData = $validator->validated();
        $accountData['company_id'] = $user->company_id;

        $account = Account::create($accountData);

        // Get dropdown details for the created account
        $accountType = AccountDropdown::where('id', $account->account_type)
            ->where('type', 'account_type')
            ->first();

        $detailType = AccountDropdown::where('id', $account->detail_type)
            ->where('type', 'detail_type')
            ->first();

        $parentAccount = null;
        if ($account->parent_account) {
            $parentAccount = AccountDropdown::where('id', $account->parent_account)
                ->where('type', 'parent_account')
                ->first();
        }

        $response = [
            'account' => [
                'account_id' => $account->account_id,
                'account_name' => $account->account_name,
                'subaccount' => $account->subaccount,
                'description' => $account->description,
                'company_id' => $account->company_id,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at
            ],
            'account_type' => $accountType ? [
                'id' => $accountType->id,
                'name' => $accountType->name,
                'type' => $accountType->type
            ] : null,
            'detail_type' => $detailType ? [
                'id' => $detailType->id,
                'name' => $detailType->name,
                'type' => $detailType->type,
                'parent_id' => $detailType->parent_id
            ] : null,
            'parent_account' => $parentAccount ? [
                'id' => $parentAccount->id,
                'name' => $parentAccount->name,
                'type' => $parentAccount->type
            ] : null
        ];

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully',
            'data' => $response,
        ], 201);
    }

    /**
     * Get account dropdown options
     */
    public function getDropdowns(Request $request)
    {
        try {
            // Get all unique account types with their IDs
            $accountTypes = AccountDropdown::where('type', 'account_type')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            // Get detail types grouped by account type with their IDs
            $detailTypes = AccountDropdown::where('type', 'detail_type')
                ->select('id', 'name', 'parent_id')
                ->orderBy('name')
                ->get()
                ->groupBy('parent_id');

            // Get parent accounts from account_dropdowns table with type 'parent_account'
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            $parentAccounts = [];
            
            if ($user && $user->company_id) {
                $parentAccounts = AccountDropdown::where('company_id', $user->company_id)
                    ->where('type', 'parent_account')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();
            } else {
                // If no user or company_id, get all parent accounts
                $parentAccounts = AccountDropdown::where('type', 'parent_account')
                    ->select('id', 'name', 'company_id')
                    ->orderBy('name')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'account_types' => $accountTypes,
                    'detail_types' => $detailTypes,
                    'parent_accounts' => $parentAccounts,
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
     * Get account dropdown details by IDs
     */
    public function getAccountDetails(Request $request)
    {
        try {
            $accountTypeId = $request->input('account_type');
            $detailTypeId = $request->input('detail_type');
            $parentAccountId = $request->input('parent_account');
            $accountId = $request->input('account_id');

            $details = [];

            // Get account details from accounts table if account_id is provided
            if ($accountId) {
                $account = Account::find($accountId);
                if ($account) {
                    $details['account'] = [
                        'account_id' => $account->account_id,
                        'account_name' => $account->account_name,
                        'subaccount' => $account->subaccount,
                        'description' => $account->description,
                        'company_id' => $account->company_id,
                        'account_type_id' => $account->account_type,
                        'detail_type_id' => $account->detail_type,
                        'parent_account_id' => $account->parent_account
                    ];
                    
                    // Use the account's IDs to get dropdown details
                    $accountTypeId = $account->account_type;
                    $detailTypeId = $account->detail_type;
                    $parentAccountId = $account->parent_account;
                }
            }

            // Get account type details
            if ($accountTypeId) {
                $accountType = AccountDropdown::where('id', $accountTypeId)
                    ->where('type', 'account_type')
                    ->first();
                if ($accountType) {
                    $details['account_type'] = [
                        'id' => $accountType->id,
                        'name' => $accountType->name,
                        'type' => $accountType->type
                    ];
                }
            }

            // Get detail type details
            if ($detailTypeId) {
                $detailType = AccountDropdown::where('id', $detailTypeId)
                    ->where('type', 'detail_type')
                    ->first();
                if ($detailType) {
                    $details['detail_type'] = [
                        'id' => $detailType->id,
                        'name' => $detailType->name,
                        'type' => $detailType->type,
                        'parent_id' => $detailType->parent_id
                    ];
                }
            }

            // Get parent account details
            if ($parentAccountId) {
                $parentAccount = AccountDropdown::where('id', $parentAccountId)
                    ->where('type', 'parent_account')
                    ->first();
                if ($parentAccount) {
                    $details['parent_account'] = [
                        'id' => $parentAccount->id,
                        'name' => $parentAccount->name,
                        'type' => $parentAccount->type
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $details
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch account details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account details by account_id
     */
    public function getAccountById(Request $request, $accountId)
    {
        try {
            $account = Account::find($accountId);
            
            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            // Get dropdown details for the account
            $accountType = AccountDropdown::where('id', $account->account_type)
                ->where('type', 'account_type')
                ->first();

            $detailType = AccountDropdown::where('id', $account->detail_type)
                ->where('type', 'detail_type')
                ->first();

            $parentAccount = null;
            if ($account->parent_account) {
                $parentAccount = AccountDropdown::where('id', $account->parent_account)
                    ->where('type', 'parent_account')
                    ->first();
            }

            $response = [
                'account' => [
                    'account_id' => $account->account_id,
                    'account_name' => $account->account_name,
                    'subaccount' => $account->subaccount,
                    'description' => $account->description,
                    'company_id' => $account->company_id,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at
                ],
                'account_type' => $accountType ? [
                    'id' => $accountType->id,
                    'name' => $accountType->name,
                    'type' => $accountType->type
                ] : null,
                'detail_type' => $detailType ? [
                    'id' => $detailType->id,
                    'name' => $detailType->name,
                    'type' => $detailType->type,
                    'parent_id' => $detailType->parent_id
                ] : null,
                'parent_account' => $parentAccount ? [
                    'id' => $parentAccount->id,
                    'name' => $parentAccount->name,
                    'type' => $parentAccount->type
                ] : null
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch account details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail types for a specific account type
     */
    public function getDetailTypes(Request $request, $accountType)
    {
        try {
            // First get the account type name from the ID
            $accountTypeRecord = AccountDropdown::where('id', $accountType)
                ->first();

            if (!$accountTypeRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account type not found'
                ], 404);
            }

            $detailTypes = AccountDropdown::where('type', 'detail_type')
                ->where('parent_id', $accountTypeRecord->id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'account_type_id' => $accountType,
                    'account_type_name' => $accountTypeRecord->name,
                    'detail_types' => $detailTypes,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch detail types',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
