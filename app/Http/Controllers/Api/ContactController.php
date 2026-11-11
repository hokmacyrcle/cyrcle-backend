<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    /**
     * Display a listing of contacts with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            // Apply company filter based on authenticated user's company context
            if ($user && isset($user->company_id)) {
                $query = Contact::with(['company:company_id,company_name'])
                              ->where('company_id', $user->company_id);
            } else {
                // If no company context, return empty result for security
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('contact_first_name', 'like', "%{$search}%")
                      ->orWhere('contact_last_name', 'like', "%{$search}%")
                      ->orWhere('contact_company_name', 'like', "%{$search}%")
                      ->orWhere('contact_email_address', 'like', "%{$search}%")
                      ->orWhere('contact_phone_number', 'like', "%{$search}%");
                });
            }

            // company_id filter is automatically applied based on authenticated user

            if ($request->filled('contact_type')) {
                $query->where('contact_type', $request->contact_type);
            }

            if ($request->filled('contact_status')) {
                $query->where('contact_status', $request->contact_status);
            }

            if ($request->filled('contact_category')) {
                $query->where('contact_category', $request->contact_category);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'contact_first_name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Apply pagination
            $perPage = $request->get('per_page', 15);
            $contacts = $query->paginate($perPage);

            // Transform the data
            $transformedContacts = $contacts->getCollection()->map(function ($contact) {
                return [
                    'id' => $contact->contact_id,
                    'name' => $this->getContactDisplayName($contact),
                    'type' => $contact->contact_type,
                    'email' => $contact->contact_email_address ?? 'N/A',
                    'phone' => $contact->contact_phone_number ?? 'N/A',
                    'mobile' => $contact->contact_mobile_number ?? 'N/A',
                    'company' => [
                        'id' => $contact->company->company_id ?? null,
                        'name' => $contact->company->company_name ?? 'N/A',
                    ],
                    'address' => $this->getFormattedAddress($contact),
                    'category' => $contact->contact_category ?? 'N/A',
                    'status' => $contact->contact_status,
                    'subscriber_client' => $contact->subscriber_client,
                    'created_at' => $contact->created_at,
                    'updated_at' => $contact->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'contacts' => $transformedContacts,
                    'pagination' => [
                        'current_page' => $contacts->currentPage(),
                        'last_page' => $contacts->lastPage(),
                        'per_page' => $contacts->perPage(),
                        'total' => $contacts->total(),
                        'from' => $contacts->firstItem(),
                        'to' => $contacts->lastItem(),
                    ]
                ],
                'message' => 'Contacts retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contacts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified contact
     */
    public function show($id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be associated with a company to access contacts'
                ], 403);
            }

            $contact = Contact::with(['company:company_id,company_name'])
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            $contactData = [
                'id' => $contact->contact_id,
                'company_id' => $contact->company_id,
                'contact_type' => $contact->contact_type,
                'first_name' => $contact->contact_first_name,
                'middle_name' => $contact->contact_middle_name,
                'last_name' => $contact->contact_last_name,
                'company_name' => $contact->contact_company_name,
                'phone_number' => $contact->contact_phone_number,
                'mobile_number' => $contact->contact_mobile_number,
                'email_address' => $contact->contact_email_address,
                'address_line_1' => $contact->contact_address_line_1,
                'address_line_2' => $contact->contact_address_line_2,
                'address_line_3' => $contact->contact_address_line_3,
                'city' => $contact->contact_city,
                'state' => $contact->contact_state,
                'post_code' => $contact->contact_post_code,
                'country' => $contact->contact_country,
                'category' => $contact->contact_category,
                'status' => $contact->contact_status,
                'subscriber_client' => $contact->subscriber_client,
                'subscriber_id' => $contact->subscriber_id,
                'company' => [
                    'id' => $contact->company->company_id ?? null,
                    'name' => $contact->company->company_name ?? 'N/A',
                ],
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $contactData,
                'message' => 'Contact retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found'
            ], 404);
        }
    }

    /**
     * Store a newly created contact
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Clean and normalize the input data
            $data = $this->cleanContactData($request->all());
            
            $validator = Validator::make($data, [
                'contact_type' => 'required|in:Individual,Company,Sole Trader',
                'contact_first_name' => 'nullable|string|max:255',
                'contact_middle_name' => 'nullable|string|max:255',
                'contact_last_name' => 'nullable|string|max:255',
                'contact_company_name' => 'nullable|string|max:255',
                'contact_phone_number' => 'nullable|string|max:20',
                'contact_mobile_number' => 'nullable|string|max:20',
                'contact_email_address' => 'nullable|email|max:255',
                'contact_address_line_1' => 'nullable|string|max:255',
                'contact_address_line_2' => 'nullable|string|max:255',
                'contact_address_line_3' => 'nullable|string|max:255',
                'contact_city' => 'nullable|string|max:100',
                'contact_state' => 'nullable|string|max:100',
                'contact_post_code' => 'nullable|string|max:20',
                'contact_country' => 'nullable|string|max:100',
                'contact_category' => 'nullable|in:Supplier,Vendor,Other',
                'contact_status' => 'nullable|in:Active,Inactive',
                'subscriber_client' => 'boolean',
                'subscriber_id' => 'nullable|integer',
            ], [
                'contact_category.in' => 'The contact category must be one of: Supplier, Vendor, or Other. You sent: :input',
                'contact_type.in' => 'The contact type must be one of: Individual, Company, or Sole Trader. You sent: :input',
                'contact_status.in' => 'The contact status must be either Active or Inactive. You sent: :input'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate required fields based on contact type
            if ($data['contact_type'] === 'Company') {
                if (empty($data['contact_company_name'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Company name is required for Company type contacts'
                    ], 422);
                }
            } else {
                if (empty($data['contact_first_name']) && empty($data['contact_last_name'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'First name or last name is required for Individual/Sole Trader contacts'
                    ], 422);
                }
            }

            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be associated with a company to create contacts'
                ], 403);
            }

            DB::beginTransaction();

            // Add company_id to the cleaned data
            $data['company_id'] = $user->company_id;
            
            $contact = Contact::create($data);

            DB::commit();

            // Return the created contact
            return $this->show($contact->contact_id);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating contact: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified contact
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be associated with a company to update contacts'
                ], 403);
            }

            $contact = Contact::where('company_id', $user->company_id)->findOrFail($id);

            // Clean and normalize the input data
            $data = $this->cleanContactData($request->all());
            
            $validator = Validator::make($data, [
                'contact_type' => 'sometimes|in:Individual,Company,Sole Trader',
                'contact_first_name' => 'nullable|string|max:255',
                'contact_middle_name' => 'nullable|string|max:255',
                'contact_last_name' => 'nullable|string|max:255',
                'contact_company_name' => 'nullable|string|max:255',
                'contact_phone_number' => 'nullable|string|max:20',
                'contact_mobile_number' => 'nullable|string|max:20',
                'contact_email_address' => 'nullable|email|max:255',
                'contact_address_line_1' => 'nullable|string|max:255',
                'contact_address_line_2' => 'nullable|string|max:255',
                'contact_address_line_3' => 'nullable|string|max:255',
                'contact_city' => 'nullable|string|max:100',
                'contact_state' => 'nullable|string|max:100',
                'contact_post_code' => 'nullable|string|max:20',
                'contact_country' => 'nullable|string|max:100',
                'contact_category' => 'nullable|in:Supplier,Vendor,Other',
                'contact_status' => 'nullable|in:Active,Inactive',
                'subscriber_client' => 'boolean',
                'subscriber_id' => 'nullable|integer',
            ], [
                'contact_category.in' => 'The contact category must be one of: Supplier, Vendor, or Other. You sent: :input',
                'contact_type.in' => 'The contact type must be one of: Individual, Company, or Sole Trader. You sent: :input',
                'contact_status.in' => 'The contact status must be either Active or Inactive. You sent: :input'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate required fields based on contact type
            $contactType = $request->contact_type ?? $contact->contact_type;
            if ($contactType === 'Company') {
                if (empty($request->contact_company_name) && empty($contact->contact_company_name)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Company name is required for Company type contacts'
                    ], 422);
                }
            } else {
                if (empty($request->contact_first_name) && empty($request->contact_last_name) && 
                    empty($contact->contact_first_name) && empty($contact->contact_last_name)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'First name or last name is required for Individual/Sole Trader contacts'
                    ], 422);
                }
            }

            DB::beginTransaction();

            $contact->update($data);

            DB::commit();

            // Return the updated contact
            return $this->show($contact->contact_id);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating contact: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified contact
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be associated with a company to delete contacts'
                ], 403);
            }

            $contact = Contact::where('company_id', $user->company_id)->findOrFail($id);

            DB::beginTransaction();

            $contact->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contact deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting contact: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contact statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be associated with a company to access contact statistics'
                ], 403);
            }

            $companyId = $user->company_id;
            
            $stats = [
                'total_contacts' => Contact::where('company_id', $companyId)->count(),
                'active_contacts' => Contact::where('company_id', $companyId)->where('contact_status', 'Active')->count(),
                'inactive_contacts' => Contact::where('company_id', $companyId)->where('contact_status', 'Inactive')->count(),
                'by_type' => [
                    'individual' => Contact::where('company_id', $companyId)->where('contact_type', 'Individual')->count(),
                    'company' => Contact::where('company_id', $companyId)->where('contact_type', 'Company')->count(),
                    'sole_trader' => Contact::where('company_id', $companyId)->where('contact_type', 'Sole Trader')->count(),
                ],
                'by_category' => [
                    'supplier' => Contact::where('company_id', $companyId)->where('contact_category', 'Supplier')->count(),
                    'vendor' => Contact::where('company_id', $companyId)->where('contact_category', 'Vendor')->count(),
                    'other' => Contact::where('company_id', $companyId)->where('contact_category', 'Other')->count(),
                ],
                'subscriber_clients' => Contact::where('company_id', $companyId)->where('subscriber_client', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Contact statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update contact status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_ids' => 'required|array',
                'contact_ids.*' => 'integer|exists:contacts,contact_id',
                'status' => 'required|in:Active,Inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be associated with a company to bulk update contacts'
                ], 403);
            }

            $updatedCount = Contact::where('company_id', $user->company_id)
                ->whereIn('contact_id', $request->contact_ids)
                ->update(['contact_status' => $request->status]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'updated_count' => $updatedCount,
                    'total_requested' => count($request->contact_ids)
                ],
                'message' => "Successfully updated {$updatedCount} contacts to {$request->status} status"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating contact statuses: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contact audit history
     */
    public function auditHistory($id): JsonResponse
    {
        try {
            $contact = Contact::findOrFail($id);
            
            // Get audit logs for this contact
            $auditLogs = \App\Models\AuditLog::where('auditable_type', Contact::class)
                ->where('auditable_id', $id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'event' => $log->log_event,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'user_id' => $log->user_id,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'created_at' => $log->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'contact_id' => $id,
                    'contact_name' => $this->getContactDisplayName($contact),
                    'audit_logs' => $auditLogs,
                    'total_logs' => $auditLogs->count()
                ],
                'message' => 'Contact audit history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving audit history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get contact display name
     */
    private function getContactDisplayName($contact): string
    {
        if ($contact->contact_type === 'Company') {
            return $contact->contact_company_name ?? 'Unknown Company';
        }
        else {
            $firstName = $contact->contact_first_name ?? '';
            $lastName = $contact->contact_last_name ?? '';
            return trim($firstName . ' ' . $lastName) ?: 'Unknown Person';
        }
    }

    /**
     * Helper method to get formatted address
     */
    private function getFormattedAddress($contact): string
    {
        $addressParts = [];
        
        if ($contact->contact_address_line_1) $addressParts[] = $contact->contact_address_line_1;
        if ($contact->contact_address_line_2) $addressParts[] = $contact->contact_address_line_2;
        if ($contact->contact_address_line_3) $addressParts[] = $contact->contact_address_line_3;
        if ($contact->contact_city) $addressParts[] = $contact->contact_city;
        if ($contact->contact_state) $addressParts[] = $contact->contact_state;
        if ($contact->contact_post_code) $addressParts[] = $contact->contact_post_code;
        if ($contact->contact_country) $addressParts[] = $contact->contact_country;

        return implode(', ', $addressParts) ?: 'No address provided';
    }

    /**
     * Clean and normalize contact data before validation
     */
    private function cleanContactData(array $data): array
    {
        // Clean enum fields - trim whitespace and capitalize first letter
        if (isset($data['contact_category']) && !empty($data['contact_category'])) {
            $data['contact_category'] = trim($data['contact_category']);
            // Handle common variations
            $category = strtolower($data['contact_category']);
            if (in_array($category, ['supplier', 'suppliers'])) {
                $data['contact_category'] = 'Supplier';
            } elseif (in_array($category, ['vendor', 'vendors'])) {
                $data['contact_category'] = 'Vendor';
            } elseif (in_array($category, ['other', 'others', 'client', 'clients', 'customer', 'customers'])) {
                $data['contact_category'] = 'Other';
            }
        }

        if (isset($data['contact_type']) && !empty($data['contact_type'])) {
            $data['contact_type'] = trim($data['contact_type']);
            // Handle common variations
            $type = strtolower($data['contact_type']);
            if (in_array($type, ['individual', 'person', 'personal'])) {
                $data['contact_type'] = 'Individual';
            } elseif (in_array($type, ['company', 'business', 'corporate'])) {
                $data['contact_type'] = 'Company';
            } elseif (in_array($type, ['sole trader', 'sole-trader', 'sole_trader', 'freelancer', 'self-employed'])) {
                $data['contact_type'] = 'Sole Trader';
            }
        }

        if (isset($data['contact_status']) && !empty($data['contact_status'])) {
            $data['contact_status'] = trim($data['contact_status']);
            // Handle common variations
            $status = strtolower($data['contact_status']);
            if (in_array($status, ['active', 'activated', 'enabled', 'live'])) {
                $data['contact_status'] = 'Active';
            } elseif (in_array($status, ['inactive', 'deactivated', 'disabled', 'suspended'])) {
                $data['contact_status'] = 'Inactive';
            }
        }

        // Clean string fields - trim whitespace
        $stringFields = [
            'contact_first_name', 'contact_middle_name', 'contact_last_name',
            'contact_company_name', 'contact_phone_number', 'contact_mobile_number',
            'contact_email_address', 'contact_address_line_1', 'contact_address_line_2',
            'contact_address_line_3', 'contact_city', 'contact_state',
            'contact_post_code', 'contact_country'
        ];

        foreach ($stringFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
                // Convert empty strings to null
                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
        }

        return $data;
    }
}
