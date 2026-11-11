<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\QuoteCollection;
use App\Http\Resources\QuoteListResource;
use App\Http\Resources\QuoteDetailResource;
use App\Http\Resources\QuoteVersionResource;
// QuoteAttachmentService removed - using CommonAttachmentService instead
use App\Services\StatusHistoryService;
use App\Services\QuoteNumberService;
use App\Services\QuoteVersioningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\CommonAttachmentService;
use App\Models\Attachment;
use App\Services\AuditLogService;

class QuoteController extends Controller
{
    /**
     * Display a listing of quotes.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Select only the required fields for the listing page
            $query = Quote::select([
                'quotes.quote_id',
                'quotes.quote_date',
                'quotes.quote_number',
                'quotes.total',
                'quotes.status',
                'quotes.contact_id'
            ])
            ->with(['contact:contact_id,contact_first_name,contact_last_name,contact_company_name']);

            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            // Apply company filter based on authenticated user's company context
            if ($user && isset($user->company_id)) {
                $query->where('company_id', $user->company_id);
            } else {
                // If no company context, return empty result for security
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Apply filters
            if ($request->has('status') && $request->status !== 'All') {
                $query->where('status', $request->status);
            }

            if ($request->has('date')) {
                $dateFilter = $request->date;
                switch ($dateFilter) {
                    case 'Last 12 months':
                        $query->where('quote_date', '>=', now()->subMonths(12));
                        break;
                    case 'Last 6 months':
                        $query->where('quote_date', '>=', now()->subMonths(6));
                        break;
                    case 'Last 3 months':
                        $query->where('quote_date', '>=', now()->subMonths(3));
                        break;
                    case 'Last month':
                        $query->where('quote_date', '>=', now()->subMonth());
                        break;
                    case 'This month':
                        $query->whereMonth('quote_date', now()->month)
                              ->whereYear('quote_date', now()->year);
                        break;
                }
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('quote_number', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%")
                      ->orWhereHas('contact', function($contactQuery) use ($search) {
                          $contactQuery->where('contact_first_name', 'like', "%{$search}%")
                                      ->orWhere('contact_last_name', 'like', "%{$search}%")
                                      ->orWhere('contact_company_name', 'like', "%{$search}%");
                      });
                });
            }

            // Apply sorting - default to quote_date descending (newest first)
            $sortBy = $request->get('sort_by', 'quote_date');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // Ensure valid sort fields to prevent SQL injection
            $allowedSortFields = ['quote_date', 'quote_number', 'total', 'status', 'created_at'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'quote_date';
            }
            
            // Ensure valid sort order
            $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
            
            $query->orderBy($sortBy, $sortOrder);
            
            // Always add quote_date as secondary sort for consistent ordering
            if ($sortBy !== 'quote_date') {
                $query->orderBy('quote_date', 'desc');
            }

            $quotes = $query->paginate($request->get('per_page', 15));

            // Transform the data using the QuoteListResource
            $transformedQuotes = QuoteListResource::collection($quotes->getCollection());

            // Create paginated response with transformed data
            $paginatedResponse = [
                'current_page' => $quotes->currentPage(),
                'data' => $transformedQuotes,
                'first_page_url' => $quotes->url(1),
                'from' => $quotes->firstItem(),
                'last_page' => $quotes->lastPage(),
                'last_page_url' => $quotes->url($quotes->lastPage()),
                'next_page_url' => $quotes->nextPageUrl(),
                'path' => $quotes->path(),
                'per_page' => $quotes->perPage(),
                'prev_page_url' => $quotes->previousPageUrl(),
                'to' => $quotes->lastItem(),
                'total' => $quotes->total(),
            ];

            return response()->json([
                'success' => true,
                'data' => $paginatedResponse,
                'message' => 'Quotes retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving quotes: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Store a newly created quote.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            if (!$user || !isset($user->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'contact_id' => 'required|exists:contacts,contact_id',
                'quote_date' => 'required|date',
                'expiration_date' => 'required|date|after:quote_date',
                'quote_status' => 'nullable|in:Pending,Accepted,Closed,Rejected',
                'items' => 'required|array|min:1',
                'items.*.item_description' => 'required|string|max:255',
                'items.*.item_quantity' => 'required|integer|min:1',
                'items.*.item_rate' => 'required|numeric|min:0',
                'items.*.item_gst_rate' => 'nullable|numeric|min:0|max:100',
                'discount_type' => 'nullable|numeric|min:0|max:100',
                'discount_method' => 'nullable|in:percentage,fixed',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_rate' => 'nullable|numeric|min:0|max:100',
                'payment_term_id' => 'nullable|exists:payment_terms,id',
                // quote_attachment field removed - use attachments table instead
                'notes' => 'nullable|string',
                'terms' => 'nullable|string',
                'attachments.*' => 'nullable|file|max:20480',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Generate quote number
            $lastQuote = Quote::orderBy('quote_id', 'desc')->first();
            $quoteNumber = $lastQuote ? (intval($lastQuote->quote_number) + 1) : 1001;
            
            // Calculate totals
            $quoteAmount = 0;
            $totalGstAmount = 0;
            
            foreach ($request->items as $item) {
                $itemCost = $item['item_quantity'] * $item['item_rate'];
                $quoteAmount += $itemCost;
                
                // Calculate GST for each item (default 10% if not specified)
                $gstRate = $item['item_gst_rate'] ?? 10;
                $itemGstAmount = $itemCost * ($gstRate / 100);
                $totalGstAmount += $itemGstAmount;
            }

            // Calculate discount
            $discountAmount = 0;
            if ($request->has('discount_type') && $request->discount_type > 0) {
                if ($request->discount_method === 'percentage') {
                    $discountAmount = $quoteAmount * ($request->discount_type / 100);
                } else {
                    $discountAmount = $request->discount_type; // fixed amount
                }
            }

            // Calculate subtotal (after discount)
            $subtotal = $quoteAmount - $discountAmount;

            // Calculate tax (separate from GST)
            $taxAmount = 0;
            if ($request->has('tax_rate') && $request->tax_rate > 0) {
                $taxAmount = $subtotal * ($request->tax_rate / 100);
            }

            // Calculate final total (subtotal + tax + GST)
            $quoteTotal = $subtotal + $taxAmount + $totalGstAmount;
            
            // Generate unique quote number
            $quoteNumberService = new QuoteNumberService();
            $quoteNumber = $quoteNumberService->generateQuoteNumber($user->company_id);
            
            // Create quote
            $quote = Quote::create([
                'company_id' => $user->company_id,
                'contact_id' => $request->contact_id,
                'quote_number' => $quoteNumber,
                'quote_date' => $request->quote_date,
                'expiration_date' => $request->expiration_date,
                'subtotal' => $subtotal,
                'discount_percent' => $request->discount_type ?? 0,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $quoteTotal,
                'status' => $request->quote_status ?? 'Pending',
                'payment_term_id' => $request->payment_term_id,
                'notes' => $request->notes,
                'terms' => $request->terms,
            ]);

            // Create quote items
            foreach ($request->items as $item) {
                $itemCost = $item['item_quantity'] * $item['item_rate'];
                $gstRate = $item['item_gst_rate'] ?? 10;
                $itemGst = $itemCost * ($gstRate / 100);
                $itemTotal = $itemCost + $itemGst;

                $quote->items()->create([
                    'quote_id' => $quote->quote_id,
                    'product_service' => $item['item_description'],
                    'description' => $item['item_description'],
                    'quantity' => $item['item_quantity'],
                    'rate' => $item['item_rate'],
                    'amount' => $itemCost,
                    'gst' => $itemGst,
                ]);
            }

            // Record initial status in history
            try {
                $statusHistoryService = new StatusHistoryService();
                $statusHistoryService->recordQuoteStatusChange(
                    $quote->quote_id, 
                    'Pending', 
                    'Quote created'
                );
            } catch (\Exception $e) {
                // Log the error but don't fail the quote creation
                \Log::error('Failed to record initial status history: ' . $e->getMessage());
            }

            // Create initial version
            try {
                $versioningService = new QuoteVersioningService();
                $versioningService->createVersion(
                    $quote,
                    'Initial quote creation',
                    'Quote created with initial data'
                );
            } catch (\Exception $e) {
                // Log the error but don't fail the quote creation
                \Log::error('Failed to create initial version: ' . $e->getMessage());
            }

            // Handle file attachments if provided
            $uploadedFilesInfo = [];
            if ($request->hasFile('attachments')) {
                $attachmentService = new CommonAttachmentService();
                $uploadedFilesInfo = $attachmentService->storeMultipleAttachments(
                    'Quote',
                    (int) $quote->company_id,
                    (int) $quote->quote_id,
                    $request->file('attachments')
                );

                foreach ($uploadedFilesInfo as $fileInfo) {
                    $attachment = Attachment::find($fileInfo['id']);
                    if ($attachment) {
                        AuditLogService::logAction(
                            $quote,
                            'attachment_created',
                            [],
                            [
                                'attachment_id' => $attachment->id,
                                'path' => $attachment->image_url,
                            ]
                        );
                    }
                }
            }

            DB::commit();

            // Load relationships
            $quote->load(['company', 'contact', 'items', 'paymentTerm', 'attachments']);

            // Transform the data to include all available fields (same as invoice format)
            $transformedQuote = [
                // Basic Quote Information
                'quote_id' => $quote->quote_id,
                'quote_date' => $quote->quote_date,
                'expiration_date' => $quote->expiration_date,
                'quote_status' => $quote->status,
                'quote_amount' => $quote->quote_amount,
                'quote_total' => $quote->total,
                'attachments' => $quote->getAllAttachmentsAttribute()->map(function($attachment) {
                    return [
                        'id' => $attachment['id'],
                        'path' => $attachment['image_url'],
                        'original_name' => $attachment['original_name'],
                        'filename' => $attachment['filename'],
                        'size' => $attachment['size'],
                        'mime_type' => $attachment['mime_type'],
                        'created_at' => $attachment['created_at'],
                        'is_legacy' => $attachment['is_legacy'] ?? false
                    ];
                }),
                'uploaded_attachments' => $uploadedFilesInfo,
                
                // Company Information
                'company_id' => $quote->company_id,
                
                // Customer Information
                'customer' => [
                    'id' => $quote->contact->contact_id ?? null,
                    'name' => $this->getCustomerName($quote->contact),
                    'email' => $quote->contact->contact_email_address ?? 'N/A',
                    'phone' => $quote->contact->contact_phone_number ?? 'N/A',
                    'type' => $quote->contact->contact_type ?? 'N/A',
                    'company_name' => $quote->contact->contact_company_name ?? 'N/A',
                ],
                'sent_to' => $quote->sent_to,
                
                // Billing Address
                'billing_address' => $this->getBillingAddress($quote->contact),
                
                // Payment Terms
                'payment_term_id' => $quote->payment_term_id,
                'terms' => [
                    'id' => $quote->paymentTerm->id ?? null,
                    'description' => $quote->paymentTerm->description ?? 'N/A',
                ],
                
                // Financial Details
                'subtotal' => $quote->subtotal ?? $quote->quote_amount,
                'discount' => [
                    'type' => $quote->discount_type ?? 0,
                    'method' => $quote->discount_method ?? 'N/A',
                    'amount' => $quote->discount_amount ?? 0,
                    'percentage' => $quote->discount_type ?? 0,
                ],
                'tax' => [
                    'rate' => $quote->tax_rate ?? 0,
                    'amount' => $quote->tax_amount ?? 0,
                ],
                'gst_amount' => $quote->gst_amount ?? 0,
                'total' => $quote->total,
                
                // Items List
                'items_list' => $quote->items->map(function ($item) {
                    return [
                        'id' => $item->item_id ?? null,
                        'description' => $item->item_description,
                        'quantity' => $item->item_quantity,
                        'rate' => $item->item_rate,
                        'cost' => $item->item_cost,
                        'gst' => $item->item_gst,
                        'total' => $item->item_total,
                    ];
                }),
                
                // Timestamps
                'created_at' => $quote->created_at,
                'updated_at' => $quote->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedQuote,
                'message' => 'Quote created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified quote.
     */
    public function show($id): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            if (!$user || !isset($user->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $quote = Quote::with(['contact', 'items', 'attachments'])
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new QuoteDetailResource($quote),
                'message' => 'Quote retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Quote not found'
            ], 404);
        }
    }

    /**
     * Update the specified quote.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            if (!$user || !isset($user->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $quote = Quote::where('company_id', $user->company_id)->findOrFail($id);

            // Check if quote can be updated (only when status is Pending)
            if ($quote->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote cannot be updated. Only quotes with Pending status can be modified.',
                    'current_status' => $quote->status
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'company_id' => 'sometimes|exists:companies,company_id',
                'contact_id' => 'sometimes|exists:contacts,contact_id',
                'quote_date' => 'sometimes|date',
                'expiration_date' => 'sometimes|date',
                'quote_status' => 'sometimes|in:Pending,Accepted,Closed,Rejected',
                'discount_type' => 'nullable|numeric|min:0|max:100',
                'discount_method' => 'nullable|in:percentage,fixed',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_rate' => 'nullable|numeric|min:0|max:100',
                'payment_term_id' => 'nullable|exists:payment_terms,id',
                // quote_attachment field removed - use attachments table instead
                'notes' => 'nullable|string',
                'terms' => 'nullable|string',
                'items' => 'sometimes|array|min:1',
                'items.*.item_description' => 'required_with:items|string|max:255',
                'items.*.item_quantity' => 'required_with:items|integer|min:1',
                'items.*.item_rate' => 'required_with:items|numeric|min:0',
                'items.*.item_gst_rate' => 'nullable|numeric|min:0|max:100',
                'attachments.*' => 'nullable|file|max:20480',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update quote basic info (excluding quote_number to prevent changes)
            $updateData = $request->only([
                'company_id', 'contact_id', 'quote_date', 'expiration_date',
                'quote_status', 'discount_type', 'discount_method', 'discount_amount',
                'tax_rate', 'tax_amount', 'payment_term_id',
                'notes', 'terms'
            ]);
            
            // Remove quote_number from update data to prevent changes
            unset($updateData['quote_number']);
            
            $quote->update($updateData);

            // Update items if provided
            if ($request->has('items')) {
                // Delete existing items
                $quote->items()->delete();

                // Calculate new totals
                $quoteAmount = 0;
                $totalGstAmount = 0;
                
                foreach ($request->items as $item) {
                    $itemCost = $item['item_quantity'] * $item['item_rate'];
                    $quoteAmount += $itemCost;

                    $gstRate = $item['item_gst_rate'] ?? 10;
                    $itemGst = $itemCost * ($gstRate / 100);
                    $itemTotal = $itemCost + $itemGst;
                    $totalGstAmount += $itemGst;

                    $quote->items()->create([
                        'quote_id' => $quote->quote_id,
                        'product_service' => $item['item_description'],
                        'description' => $item['item_description'],
                        'quantity' => $item['item_quantity'],
                        'rate' => $item['item_rate'],
                        'amount' => $itemCost,
                        'gst' => $itemGst,
                    ]);
                }

                // Calculate discount
                $discountAmount = 0;
                if ($request->has('discount_type') && $request->discount_type > 0) {
                    if ($request->discount_method === 'percentage') {
                        $discountAmount = $quoteAmount * ($request->discount_type / 100);
                    } else {
                        $discountAmount = $request->discount_type; // fixed amount
                    }
                }

                // Calculate subtotal (after discount)
                $subtotal = $quoteAmount - $discountAmount;

                // Calculate tax (separate from GST)
                $taxAmount = 0;
                if ($request->has('tax_rate') && $request->tax_rate > 0) {
                    $taxAmount = $subtotal * ($request->tax_rate / 100);
                }

                // Calculate final total
                $quoteTotal = $subtotal + $taxAmount + $totalGstAmount;

                $quote->update([
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'total' => $quoteTotal,
                ]);
            }

            // Create new version after update
            try {
                $versioningService = new QuoteVersioningService();
                $changeSummary = $this->generateChangeSummary($request);
                $changeReason = $request->input('change_reason', 'Quote updated');
                
                $versioningService->createVersion(
                    $quote,
                    $changeSummary,
                    $changeReason
                );
            } catch (\Exception $e) {
                // Log the error but don't fail the quote update
                \Log::error('Failed to create new version: ' . $e->getMessage());
            }

            // Handle file attachments if provided
            $uploadedFilesInfo = [];
            if ($request->hasFile('attachments')) {
                $attachmentService = new CommonAttachmentService();
                $uploadedFilesInfo = $attachmentService->storeMultipleAttachments(
                    'Quote',
                    (int) $quote->company_id,
                    (int) $quote->quote_id,
                    $request->file('attachments')
                );

                foreach ($uploadedFilesInfo as $fileInfo) {
                    $attachment = Attachment::find($fileInfo['id']);
                    if ($attachment) {
                        AuditLogService::logAction(
                            $quote,
                            'attachment_created',
                            [],
                            [
                                'attachment_id' => $attachment->id,
                                'path' => $attachment->image_url,
                            ]
                        );
                    }
                }
            }

            DB::commit();

            // Load relationships
            $quote->load(['company', 'contact', 'items', 'paymentTerm', 'attachments']);

            // Transform the data to include all available fields (same as invoice format)
            $transformedQuote = [
                // Basic Quote Information
                'quote_id' => $quote->quote_id,
                'quote_date' => $quote->quote_date,
                'expiration_date' => $quote->expiration_date,
                'quote_status' => $quote->status,
                'quote_amount' => $quote->quote_amount,
                'quote_total' => $quote->total,
                'attachments' => $quote->getAllAttachmentsAttribute()->map(function($attachment) {
                    return [
                        'id' => $attachment['id'],
                        'path' => $attachment['image_url'],
                        'original_name' => $attachment['original_name'],
                        'filename' => $attachment['filename'],
                        'size' => $attachment['size'],
                        'mime_type' => $attachment['mime_type'],
                        'created_at' => $attachment['created_at'],
                        'is_legacy' => $attachment['is_legacy'] ?? false
                    ];
                }),
                'uploaded_attachments' => $uploadedFilesInfo,
                
                // Company Information
                'company_id' => $quote->company_id,
                
                // Customer Information
                'customer' => [
                    'id' => $quote->contact->contact_id ?? null,
                    'name' => $this->getCustomerName($quote->contact),
                    'email' => $quote->contact->contact_email_address ?? 'N/A',
                    'phone' => $quote->contact->contact_phone_number ?? 'N/A',
                    'type' => $quote->contact->contact_type ?? 'N/A',
                    'company_name' => $quote->contact->contact_company_name ?? 'N/A',
                ],
                'sent_to' => $quote->sent_to,
                
                // Billing Address
                'billing_address' => $this->getBillingAddress($quote->contact),
                
                // Payment Terms
                'payment_term_id' => $quote->payment_term_id,
                'terms' => [
                    'id' => $quote->paymentTerm->id ?? null,
                    'description' => $quote->paymentTerm->description ?? 'N/A',
                ],
                
                // Financial Details
                'subtotal' => $quote->subtotal ?? $quote->quote_amount,
                'discount' => [
                    'type' => $quote->discount_type ?? 0,
                    'method' => $quote->discount_method ?? 'N/A',
                    'amount' => $quote->discount_amount ?? 0,
                    'percentage' => $quote->discount_type ?? 0,
                ],
                'tax' => [
                    'rate' => $quote->tax_rate ?? 0,
                    'amount' => $quote->tax_amount ?? 0,
                ],
                'gst_amount' => $quote->gst_amount ?? 0,
                'total' => $quote->total,
                
                // Items List
                'items_list' => $quote->items->map(function ($item) {
                    return [
                        'id' => $item->item_id ?? null,
                        'description' => $item->item_description,
                        'quantity' => $item->item_quantity,
                        'rate' => $item->item_rate,
                        'cost' => $item->item_cost,
                        'gst' => $item->item_gst,
                        'total' => $item->item_total,
                    ];
                }),
                
                // Timestamps
                'created_at' => $quote->created_at,
                'updated_at' => $quote->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedQuote,
                'message' => 'Quote updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified quote.
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            if (!$user || !isset($user->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $quote = Quote::where('company_id', $user->company_id)->findOrFail($id);
            $quote->delete();

            return response()->json([
                'success' => true,
                'message' => 'Quote deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update quote status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:Pending,Accepted,Closed,Rejected'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $quote = Quote::findOrFail($id);
            $oldStatus = $quote->status;
            $quote->update(['status' => $request->status]);

            // Record status change in history
            try {
                $statusHistoryService = new StatusHistoryService();
                $statusHistoryService->recordQuoteStatusChange(
                    $quote->quote_id, 
                    $request->status, 
                    "Status changed from {$oldStatus} to {$request->status}"
                );
            } catch (\Exception $e) {
                // Log the error but don't fail the status update
                \Log::error('Failed to record status change history: ' . $e->getMessage());
            }

            $quote->load(['contact', 'items']);
            
            return response()->json([
                'success' => true,
                'data' => new QuoteDetailResource($quote),
                'message' => 'Quote status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating quote status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quote statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            if (!$user || !isset($user->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $stats = [
                'total' => Quote::where('company_id', $user->company_id)->count(),
                'pending' => Quote::where('company_id', $user->company_id)->pending()->count(),
                'accepted' => Quote::where('company_id', $user->company_id)->accepted()->count(),
                'closed' => Quote::where('company_id', $user->company_id)->closed()->count(),
                'rejected' => Quote::where('company_id', $user->company_id)->rejected()->count(),
                'expired' => Quote::where('company_id', $user->company_id)->expired()->count(),
                'total_value' => Quote::where('company_id', $user->company_id)->sum('total'),
                'pending_value' => Quote::where('company_id', $user->company_id)->pending()->sum('total'),
                'accepted_value' => Quote::where('company_id', $user->company_id)->accepted()->sum('total'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Quote statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving quote statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available sorting options for quotes.
     */
    public function getSortingOptions(): JsonResponse
    {
        try {
            $sortingOptions = [
                'fields' => [
                    [
                        'value' => 'quote_date',
                        'label' => 'Quote Date',
                        'description' => 'Sort by when the quote was created'
                    ],
                    [
                        'value' => 'quote_number',
                        'label' => 'Quote Number',
                        'description' => 'Sort by quote number'
                    ],
                    [
                        'value' => 'total',
                        'label' => 'Total Amount',
                        'description' => 'Sort by quote total amount'
                    ],
                    [
                        'value' => 'status',
                        'label' => 'Status',
                        'description' => 'Sort by quote status'
                    ],
                    [
                        'value' => 'created_at',
                        'label' => 'Created Date',
                        'description' => 'Sort by when the quote was created in the system'
                    ]
                ],
                'orders' => [
                    [
                        'value' => 'desc',
                        'label' => 'Descending',
                        'description' => 'Newest/Highest first'
                    ],
                    [
                        'value' => 'asc',
                        'label' => 'Ascending',
                        'description' => 'Oldest/Lowest first'
                    ]
                ],
                'defaults' => [
                    'sort_by' => 'quote_date',
                    'sort_order' => 'desc'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $sortingOptions,
                'message' => 'Quote sorting options retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving sorting options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert quote to invoice.
     */
    public function convertToInvoice($id): JsonResponse
    {
        try {
            $quote = Quote::with(['company', 'contact', 'paymentTerm', 'items'])->findOrFail($id);

            // Check if quote can be converted (only when status is Pending)
            if ($quote->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote cannot be converted to invoice. Only quotes with Pending status can be converted.',
                    'current_status' => $quote->status
                ], 422);
            }

            if (!$quote->canBeConvertedToInvoice()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote cannot be converted to invoice'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Generate invoice number
                $lastInvoice = \App\Models\Invoice::orderBy('invoice_id', 'desc')->first();
                $invoiceNumber = $lastInvoice ? (intval($lastInvoice->invoice_id) + 1001) : 1001;

                // Calculate invoice due date (30 days from today if no payment term)
                $dueDate = $quote->paymentTerm ? 
                    now()->addDays($quote->paymentTerm->days) : 
                    now()->addDays(30);

                // Create invoice from quote data
                $invoice = \App\Models\Invoice::create([
                    'company_id' => $quote->company_id,
                    'sent_to' => $quote->contact_id,
                    'invoice_date' => now(),
                    'invoice_due_date' => $dueDate,
                    'invoice_status' => 'Draft',
                    'invoice_payment_status' => 'Unpaid',
                    'invoice_amount' => $quote->subtotal ?? 0,
                    'discount_type' => $quote->discount_percent ?? 0,
                    'discount_method' => 'percentage',
                    'discount_amount' => $quote->discount_amount ?? 0,
                    'tax_rate' => 0, // Quote already includes GST
                    'tax_amount' => 0,
                    'subtotal' => $quote->subtotal ?? 0,
                    'gst_amount' => $quote->tax_amount ?? 0,
                    'invoice_total' => $quote->total ?? 0,
                    'invoice_paid_amount' => 0.00,
                    'payment_term_id' => $quote->payment_term_id,
                    'invoice_attachment' => $quote->attachments ? json_encode($quote->attachments) : null,
                    'notes' => $quote->notes,
                    'terms' => $quote->terms,
                ]);

                // Create invoice items from quote items
                foreach ($quote->items as $quoteItem) {
                    \App\Models\InvoiceItem::create([
                        'invoice_id' => $invoice->invoice_id,
                        'item_description' => $quoteItem->product_service,
                        'item_quantity' => $quoteItem->quantity,
                        'item_rate' => $quoteItem->rate,
                        'item_cost' => $quoteItem->amount,
                        'item_gst' => $quoteItem->gst,
                        'item_total' => $quoteItem->amount + $quoteItem->gst,
                    ]);
                }

                // Update quote status to 'Accepted' since it's been converted
                $quote->update(['status' => 'Accepted']);

                // Record status change in history
                try {
                    $statusHistoryService = new StatusHistoryService();
                    $statusHistoryService->recordQuoteStatusChange(
                        $quote->quote_id, 
                        'Accepted', 
                        'Quote converted to invoice'
                    );

                    // Record invoice creation in history
                    $statusHistoryService->recordInvoiceStatusChange(
                        $invoice->invoice_id, 
                        'Draft', 
                        'Invoice created from quote'
                    );
                } catch (\Exception $e) {
                    // Log the error but don't fail the conversion
                    \Log::error('Failed to record status history: ' . $e->getMessage());
                }

                DB::commit();

                // Load the created invoice with relationships
                $invoice->load(['company', 'contact', 'items', 'paymentTerm']);

                return response()->json([
                    'success' => true,
                    'message' => 'Quote converted to invoice successfully',
                    'data' => [
                        'quote_id' => $quote->quote_id,
                        'quote_number' => $quote->quote_number,
                        'invoice_id' => $invoice->invoice_id,
                        'invoice_total' => $invoice->invoice_total,
                        'invoice_status' => $invoice->invoice_status,
                        'invoice_payment_status' => $invoice->invoice_payment_status,
                        'items_count' => $invoice->items->count(),
                        'customer_name' => $quote->contact->contact_first_name . ' ' . $quote->contact->contact_last_name,
                        'company_name' => $quote->company->company_name ?? 'N/A'
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error converting quote to invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload attachments for a quote
     */
    public function uploadAttachments(Request $request, $quoteId): JsonResponse
    {
        try {
            $quote = Quote::findOrFail($quoteId);
            
            $request->validate([
                'attachments.*' => 'required|file|max:20480', // 20MB max
            ]);

            $attachmentService = new CommonAttachmentService();
            $uploadedFilesInfo = $attachmentService->storeMultipleAttachments(
                'Quote', 
                $quote->company_id, 
                $quote->quote_id, 
                $request->file('attachments')
            );

            // Log audit for each uploaded attachment
            foreach ($uploadedFilesInfo as $fileInfo) {
                $attachment = Attachment::find($fileInfo['id']);
                if ($attachment) {
                    AuditLogService::logAction(
                        $quote,
                        'attachment_created',
                        [],
                        [
                            'attachment_id' => $attachment->id,
                            'path' => $attachment->image_url,
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'data' => $uploadedFilesInfo,
                'message' => 'Attachments uploaded successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to upload quote attachments', [
                'quote_id' => $quoteId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quote attachments
     */
    public function getAttachments($quoteId): JsonResponse
    {
        try {
            $quote = Quote::findOrFail($quoteId);
            
            // Get all attachments (both legacy and new)
            $allAttachments = $quote->getAllAttachmentsAttribute();
            
            return response()->json([
                'success' => true,
                'data' => $allAttachments,
                'message' => 'Quote attachments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to get quote attachments', [
                'quote_id' => $quoteId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attachments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete quote attachment
     */
    public function deleteAttachment(Request $request, $quoteId): JsonResponse
    {
        try {
            $quote = Quote::findOrFail($quoteId);
            $attachmentService = new CommonAttachmentService();
            $attachmentId = $request->attachment_id;

            if (!$attachmentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment ID is required'
                ], 400);
            }

            $success = $attachmentService->deleteAttachment('Quote', $quote->quote_id, $attachmentId);

            if ($success) {
                AuditLogService::logAction(
                    $quote,
                    'attachment_deleted',
                    [],
                    ['attachment_id' => $attachmentId]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Attachment deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete attachment'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to delete quote attachment', [
                'quote_id' => $quoteId,
                'attachment_id' => $request->attachment_id ?? 'not provided',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status history for a quote.
     */
    public function getStatusHistory($id): JsonResponse
    {
        try {
            $quote = Quote::findOrFail($id);
            $statusHistoryService = new StatusHistoryService();
            $statusHistory = $statusHistoryService->getQuoteStatusHistory($quote->quote_id);

            return response()->json([
                'success' => true,
                'data' => $statusHistory,
                'message' => 'Quote status history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving quote status history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get version history for a quote.
     */
    public function getVersionHistory($id): JsonResponse
    {
        try {
            $quote = Quote::findOrFail($id);
            $versioningService = new QuoteVersioningService();
            $versionHistory = $versioningService->getVersionHistory($quote->quote_id);

            return response()->json([
                'success' => true,
                'data' => $versionHistory,
                'message' => 'Quote version history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving quote version history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific version of a quote.
     */
    public function getVersion($id, $versionNumber): JsonResponse
    {
        try {
            $quote = Quote::findOrFail($id);
            $versioningService = new QuoteVersioningService();
            $version = $versioningService->getVersion($quote->quote_id, $versionNumber);

            if (!$version) {
                return response()->json([
                    'success' => false,
                    'message' => 'Version not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new QuoteVersionResource($version),
                'message' => 'Quote version retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving quote version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compare two versions of a quote.
     */
    public function compareVersions(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'version1' => 'required|string',
                'version2' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $quote = Quote::findOrFail($id);
            $versioningService = new QuoteVersioningService();
            $comparison = $versioningService->compareVersions(
                $quote->quote_id, 
                $request->version1, 
                $request->version2
            );

            return response()->json([
                'success' => true,
                'data' => $comparison,
                'message' => 'Version comparison completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error comparing versions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a quote to a specific version.
     */
    public function restoreToVersion(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'version_number' => 'required|string',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $quote = Quote::findOrFail($id);

            // Check if quote can be updated
            if ($quote->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote cannot be restored. Only quotes with Pending status can be modified.',
                    'current_status' => $quote->status
                ], 422);
            }

            $versioningService = new QuoteVersioningService();
            $restored = $versioningService->restoreToVersion($quote, $request->version_number);

            if ($restored) {
                // Record status change in history
                try {
                    $statusHistoryService = new StatusHistoryService();
                    $statusHistoryService->recordQuoteStatusChange(
                        $quote->quote_id, 
                        $quote->status, 
                        "Quote restored to version {$request->version_number}. Reason: " . ($request->reason ?: 'No reason provided')
                    );
                } catch (\Exception $e) {
                    \Log::error('Failed to record status history after restore: ' . $e->getMessage());
                }

                $quote->load(['company', 'contact', 'items', 'paymentTerm']);

                return response()->json([
                    'success' => true,
                    'data' => new QuoteDetailResource($quote),
                    'message' => 'Quote restored to version ' . $request->version_number . ' successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore quote'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get version statistics for a quote.
     */
    public function getVersionStatistics($id): JsonResponse
    {
        try {
            $quote = Quote::findOrFail($id);
            $versioningService = new QuoteVersioningService();
            $statistics = $versioningService->getVersionStatistics($quote->quote_id);

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Quote version statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving quote version statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get customer display name
     */
    private function getCustomerName($contact): string
    {
        if ($contact->contact_type === 'Company') {
            return $contact->contact_company_name ?? 'Unknown Company';
        } else {
            $firstName = $contact->contact_first_name ?? '';
            $lastName = $contact->contact_last_name ?? '';
            return trim($firstName . ' ' . $lastName) ?: 'Unknown Person';
        }
    }

    /**
     * Helper method to get formatted billing address
     */
    private function getBillingAddress($contact): string
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
     * Helper method to generate change summary for versioning
     */
    private function generateChangeSummary(Request $request): string
    {
        $changes = [];

        // Check for basic field changes
        $basicFields = [
            'company_id' => 'Company',
            'contact_id' => 'Contact',
            'quote_date' => 'Quote Date',
            'expiration_date' => 'Expiration Date',
            'notes' => 'Notes',
            'terms' => 'Terms'
        ];

        foreach ($basicFields as $field => $label) {
            if ($request->has($field)) {
                $changes[] = $label . ' updated';
            }
        }

        // Check for financial changes
        if ($request->has('discount_type') || $request->has('discount_amount')) {
            $changes[] = 'Discount updated';
        }

        if ($request->has('tax_rate') || $request->has('tax_amount')) {
            $changes[] = 'Tax updated';
        }

        // Check for items changes
        if ($request->has('items')) {
            $changes[] = 'Items updated';
        }

        // Check for payment terms
        if ($request->has('payment_term_id')) {
            $changes[] = 'Payment terms updated';
        }

        return implode(', ', $changes) ?: 'General updates';
    }
}
