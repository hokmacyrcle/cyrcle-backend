<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\Contact;
use App\Models\InvoiceItem;
use App\Models\PaymentTerm;
use App\Models\TaxRate;
use App\Services\CommonAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Models\Attachment;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Select only the required fields for the list view
            $query = Invoice::select([
                'invoices.invoice_id',
                'invoices.invoice_date',
                'invoices.invoice_total',
                'invoices.invoice_status',
                'invoices.invoice_payment_status',
                'invoices.invoice_due_date',
                'invoices.sent_to'
            ])
            ->with(['contact:contact_id,contact_first_name,contact_last_name,contact_company_name,contact_type']);

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
            if ($request->has('status')) {
                $query->where('invoice_status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('invoice_payment_status', $request->payment_status);
            }

            // Allow explicit company_id filter only if it matches user's company context
            if ($request->has('company_id')) {
                $requestCompanyId = $request->company_id;
                if ($user && isset($user->company_id) && $requestCompanyId == $user->company_id) {
                    $query->where('company_id', $requestCompanyId);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only access invoices for your selected company.'
                    ], 403);
                }
            }

            if ($request->has('contact_id')) {
                $query->where('sent_to', $request->contact_id);
            }

            if ($request->has('date_from')) {
                $query->where('invoice_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('invoice_date', '<=', $request->date_to);
            }

            if ($request->has('due_date_from')) {
                $query->where('invoice_due_date', '>=', $request->due_date_from);
            }

            if ($request->has('due_date_to')) {
                $query->where('invoice_due_date', '<=', $request->due_date_to);
            }

            if ($request->has('min_amount')) {
                $query->where('invoice_total', '>=', $request->min_amount);
            }

            if ($request->has('max_amount')) {
                $query->where('invoice_total', '<=', $request->max_amount);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->whereHas('contact', function($q) use ($search) {
                        $q->where('contact_first_name', 'like', "%{$search}%")
                          ->orWhere('contact_last_name', 'like', "%{$search}%")
                          ->orWhere('contact_company_name', 'like', "%{$search}%")
                          ->orWhere('contact_email_address', 'like', "%{$search}%");
                    });
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'invoice_date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $invoices = $query->paginate($request->get('per_page', 15));

            // Transform the data to include only required fields
            $transformedInvoices = $invoices->getCollection()->map(function ($invoice) {
                $statusWithDays = $this->getInvoiceStatusWithDays($invoice);
                $daysInfo = $this->getDaysInfo($invoice);
                
                return [
                    'id' => $invoice->invoice_id,
                    'invoice_date' => $invoice->invoice_date,
                    'customer_name' => $this->getCustomerName($invoice->contact),
                    'total_amount' => $invoice->invoice_total,
                    'status' => $statusWithDays,
                    'payment_status' => $invoice->invoice_payment_status,
                    'due_date' => $invoice->invoice_due_date,
                    'is_overdue' => $this->isInvoiceOverdue($invoice),
                    'days_info' => $daysInfo
                ];
            });

            // Create paginated response with transformed data
            $paginatedResponse = [
                'current_page' => $invoices->currentPage(),
                'data' => $transformedInvoices,
                'first_page_url' => $invoices->url(1),
                'from' => $invoices->firstItem(),
                'last_page' => $invoices->lastPage(),
                'last_page_url' => $invoices->url($invoices->lastPage()),
                'next_page_url' => $invoices->nextPageUrl(),
                'path' => $invoices->path(),
                'per_page' => $invoices->perPage(),
                'prev_page_url' => $invoices->previousPageUrl(),
                'to' => $invoices->lastItem(),
                'total' => $invoices->total(),
            ];

            // Add summary data
            $summary = [
                'total_count' => $invoices->total(),
                'total_amount' => $invoices->getCollection()->sum('invoice_total'),
                'status_distribution' => [
                    'due_today' => $invoices->getCollection()->filter(function($invoice) {
                        return $this->getInvoiceStatusWithDays($invoice) === 'Due Today';
                    })->count(),
                    'due_tomorrow' => $invoices->getCollection()->filter(function($invoice) {
                        return $this->getInvoiceStatusWithDays($invoice) === 'Due Tomorrow';
                    })->count(),
                    'due_future' => $invoices->getCollection()->filter(function($invoice) {
                        $status = $this->getInvoiceStatusWithDays($invoice);
                        return strpos($status, 'Due in') === 0;
                    })->count(),
                    'overdue' => $invoices->getCollection()->filter(function($invoice) {
                        return $this->isInvoiceOverdue($invoice);
                    })->count(),
                ],
                'payment_distribution' => [
                    'paid' => $invoices->getCollection()->where('invoice_payment_status', 'Paid')->count(),
                    'unpaid' => $invoices->getCollection()->where('invoice_payment_status', 'Unpaid')->count(),
                    'partially_paid' => $invoices->getCollection()->where('invoice_payment_status', 'Partially Paid')->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $paginatedResponse,
                'summary' => $summary,
                'message' => 'Invoices retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer name from contact
     */
    private function getCustomerName($contact): string
    {
        if (!$contact) {
            return 'Unknown Customer';
        }

        // Debug: Check what type of data we're receiving
        if (is_int($contact)) {
            \Log::warning('getCustomerName received integer instead of Contact model: ' . $contact);
            return 'Invalid Contact ID: ' . $contact;
        }

        // Always prioritize contact person's name (first + last name)
        $firstName = $contact->contact_first_name ?? '';
        $lastName = $contact->contact_last_name ?? '';
        $personName = trim($firstName . ' ' . $lastName);
        
        // If we have a person name, return it
        if (!empty($personName)) {
            return $personName;
        }
        
        // Fallback to company name if no person name available
        if ($contact->contact_type === 'Company' && !empty($contact->contact_company_name)) {
            return $contact->contact_company_name;
        }
        
        return 'Unknown Customer';
    }

    /**
     * Check if invoice is overdue
     */
    private function isInvoiceOverdue($invoice): bool
    {
        if (!$invoice->invoice_due_date) {
            return false;
        }

        $dueDate = \Carbon\Carbon::parse($invoice->invoice_due_date);
        $today = \Carbon\Carbon::today();

        return $dueDate->lt($today) && $invoice->invoice_payment_status !== 'Paid';
    }

    /**
     * Get invoice status with days calculation
     */
    private function getInvoiceStatusWithDays($invoice): string
    {
        if (!$invoice->invoice_due_date) {
            return $invoice->invoice_status ?? 'Unknown';
        }

        $dueDate = \Carbon\Carbon::parse($invoice->invoice_due_date);
        $today = \Carbon\Carbon::today();
        
        // If invoice is already paid, just return the status
        if ($invoice->invoice_payment_status === 'Paid') {
            return $invoice->invoice_status ?? 'Paid';
        }

        // Calculate days difference
        if ($dueDate->gt($today)) {
            // Invoice is not due yet
            $daysUntilDue = $today->diffInDays($dueDate, false);
            if ($daysUntilDue === 0) {
                return 'Due Today';
            } elseif ($daysUntilDue === 1) {
                return 'Due Tomorrow';
            } else {
                return "Due in {$daysUntilDue} days";
            }
        } elseif ($dueDate->eq($today)) {
            // Invoice is due today
            return 'Due Today';
        } else {
            // Invoice is overdue
            $daysOverdue = abs($today->diffInDays($dueDate, false));
            if ($daysOverdue === 1) {
                return '1 day overdue';
            } else {
                return "{$daysOverdue} days overdue";
            }
        }
    }

    /**
     * Get detailed days information for invoice
     */
    private function getDaysInfo($invoice): array
    {
        if (!$invoice->invoice_due_date) {
            return [
                'days_until_due' => null,
                'days_overdue' => null,
                'status_type' => 'unknown',
                'urgency_level' => 'none'
            ];
        }

        $dueDate = \Carbon\Carbon::parse($invoice->invoice_due_date);
        $today = \Carbon\Carbon::today();
        
        // If invoice is already paid
        if ($invoice->invoice_payment_status === 'Paid') {
            return [
                'days_until_due' => null,
                'days_overdue' => null,
                'status_type' => 'paid',
                'urgency_level' => 'none'
            ];
        }

        if ($dueDate->gt($today)) {
            // Invoice is not due yet
            $daysUntilDue = $today->diffInDays($dueDate, false);
            $urgencyLevel = $daysUntilDue <= 3 ? 'high' : ($daysUntilDue <= 7 ? 'medium' : 'low');
            
            return [
                'days_until_due' => $daysUntilDue,
                'days_overdue' => null,
                'status_type' => 'future',
                'urgency_level' => $urgencyLevel
            ];
        } elseif ($dueDate->eq($today)) {
            // Invoice is due today
            return [
                'days_until_due' => 0,
                'days_overdue' => null,
                'status_type' => 'due_today',
                'urgency_level' => 'critical'
            ];
        } else {
            // Invoice is overdue
            $daysOverdue = $today->diffInDays($dueDate, false);
            $urgencyLevel = $daysOverdue <= 7 ? 'high' : ($daysOverdue <= 30 ? 'medium' : 'critical');
            
            return [
                'days_until_due' => null,
                'days_overdue' => abs($daysOverdue),
                'status_type' => 'overdue',
                'urgency_level' => $urgencyLevel
            ];
        }
    }

    /**
     * Get billing address from contact
     */
    private function getBillingAddress($contact): string
    {
        if (!$contact) {
            return 'N/A';
        }

        $addressParts = [];
        
        if (!empty($contact->contact_address_line_1)) {
            $addressParts[] = $contact->contact_address_line_1;
        }
        
        if (!empty($contact->contact_address_line_2)) {
            $addressParts[] = $contact->contact_address_line_2;
        }
        
        if (!empty($contact->contact_address_line_3)) {
            $addressParts[] = $contact->contact_address_line_3;
        }
        
        if (!empty($contact->contact_city)) {
            $addressParts[] = $contact->contact_city;
        }
        
        if (!empty($contact->contact_state)) {
            $addressParts[] = $contact->contact_state;
        }
        
        if (!empty($contact->contact_post_code)) {
            $addressParts[] = $contact->contact_post_code;
        }
        
        if (!empty($contact->contact_country)) {
            $addressParts[] = $contact->contact_country;
        }

        return !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';
    }

    /**
     * Store a newly created invoice.
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
                'sent_to' => 'required|exists:contacts,contact_id',
                'invoice_date' => 'required|date',
                'invoice_due_date' => 'required|date|after:invoice_date',
                'invoice_status' => 'nullable|in:Due,Past Due,Draft,Sent,Viewed,Approved,Rejected,Cancelled',
                'invoice_payment_status' => 'nullable|in:Unpaid,Partially Paid,Paid,Overdue',
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
                // invoice_attachment field removed - use attachments table instead
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

            // Calculate totals
            $invoiceAmount = 0;
            $totalGstAmount = 0;
            
            foreach ($request->items as $item) {
                $itemCost = $item['item_quantity'] * $item['item_rate'];
                $invoiceAmount += $itemCost;
                
                // Calculate GST for each item (default 10% if not specified)
                $gstRate = $item['item_gst_rate'] ?? 10;
                $itemGstAmount = $itemCost * ($gstRate / 100);
                $totalGstAmount += $itemGstAmount;
            }

            // Calculate discount
            $discountAmount = 0;
            if ($request->has('discount_type') && $request->discount_type > 0) {
                if ($request->discount_method === 'percentage') {
                    $discountAmount = $invoiceAmount * ($request->discount_type / 100);
                } else {
                    $discountAmount = $request->discount_type; // fixed amount
                }
            }

            // Calculate subtotal (after discount)
            $subtotal = $invoiceAmount - $discountAmount;

            // Calculate tax (separate from GST)
            $taxAmount = 0;
            if ($request->has('tax_rate') && $request->tax_rate > 0) {
                $taxAmount = $subtotal * ($request->tax_rate / 100);
            }

            // Calculate final total (subtotal + tax + GST)
            $invoiceTotal = $subtotal + $taxAmount + $totalGstAmount;

            // Create invoice
            $invoice = Invoice::create([
                'company_id' => $user->company_id,
                'sent_to' => $request->sent_to,
                'invoice_date' => $request->invoice_date,
                'invoice_due_date' => $request->invoice_due_date,
                'invoice_amount' => $invoiceAmount,
                'discount_type' => $request->discount_type ?? 0,
                'discount_method' => $request->discount_method ?? 'percentage',
                'discount_amount' => $discountAmount,
                'tax_rate' => $request->tax_rate ?? 0,
                'tax_amount' => $taxAmount,
                'subtotal' => $subtotal,
                'gst_amount' => $totalGstAmount,
                'invoice_total' => $invoiceTotal,
                'invoice_status' => $request->invoice_status ?? 'Draft',
                'invoice_payment_status' => $request->invoice_payment_status ?? 'Unpaid',
                'invoice_paid_amount' => 0.00,
                'payment_term_id' => $request->payment_term_id,
                // invoice_attachment field removed - use attachments table instead
            ]);

            // Create invoice items
            foreach ($request->items as $item) {
                $itemCost = $item['item_quantity'] * $item['item_rate'];
                $gstRate = $item['item_gst_rate'] ?? 10;
                $itemGst = $itemCost * ($gstRate / 100);
                $itemTotal = $itemCost + $itemGst;

                InvoiceItem::create([
                    'invoice_id' => $invoice->invoice_id,
                    'item_description' => $item['item_description'],
                    'item_quantity' => $item['item_quantity'],
                    'item_rate' => $item['item_rate'],
                    'item_cost' => $itemCost,
                    'item_gst' => $itemGst,
                    'item_total' => $itemTotal,
                ]);
            }

            // Handle file attachments if provided
            $uploadedFilesInfo = [];
            if ($request->hasFile('attachments')) {
                $attachmentService = new CommonAttachmentService();
                $uploadedFilesInfo = $attachmentService->storeMultipleAttachments(
                    'Invoice',
                    (int) $invoice->company_id,
                    (int) $invoice->invoice_id,
                    $request->file('attachments')
                );

                foreach ($uploadedFilesInfo as $fileInfo) {
                    $attachment = Attachment::find($fileInfo['id']);
                    if ($attachment) {
                        AuditLogService::logAction(
                            $invoice,
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
            $invoice->load(['company', 'contact', 'items', 'paymentTerm', 'transactions', 'attachments']);

            // Transform the data to include all available fields (same as show method)
            $transformedInvoice = [
                // Basic Invoice Information
                'invoice_id' => $invoice->invoice_id,
                'invoice_date' => $invoice->invoice_date,
                'invoice_due_date' => $invoice->invoice_due_date,
                'invoice_status' => $invoice->invoice_status,
                'invoice_payment_status' => $invoice->invoice_payment_status,
                'invoice_amount' => $invoice->invoice_amount,
                'invoice_total' => $invoice->invoice_total,
                'invoice_paid_amount' => $invoice->invoice_paid_amount,
                // invoice_attachment field removed - use attachments table instead
                
                // Company Information
                'company_id' => $invoice->company_id,
                
                // Customer Information
                'customer' => [
                    'id' => $invoice->contact->contact_id ?? null,
                    'name' => $this->getCustomerName($invoice->contact),
                    'email' => $invoice->contact->contact_email_address ?? 'N/A',
                    'phone' => $invoice->contact->contact_phone ?? 'N/A',
                    'type' => $invoice->contact->contact_type ?? 'N/A',
                    'company_name' => $invoice->contact->contact_company_name ?? 'N/A',
                ],
                'sent_to' => $invoice->sent_to,
                
                // Billing Address
                'billing_address' => $this->getBillingAddress($invoice->contact),
                
                // Payment Terms
                'payment_term_id' => $invoice->payment_term_id,
                'terms' => [
                    'id' => $invoice->paymentTerm->id ?? null,
                    'description' => $invoice->paymentTerm->description ?? 'N/A',
                ],
                
                // Financial Details
                'subtotal' => $invoice->subtotal ?? $invoice->invoice_amount,
                'discount' => [
                    'type' => $invoice->discount_type ?? 0,
                    'method' => $invoice->discount_method ?? 'N/A',
                    'amount' => $invoice->discount_amount ?? 0,
                    'percentage' => $invoice->discount_type ?? 0,
                ],
                'tax' => [
                    'rate' => $invoice->tax_rate ?? 0,
                    'amount' => $invoice->tax_amount ?? 0,
                ],
                'gst_amount' => $invoice->gst_amount ?? 0,
                'total' => $invoice->invoice_total,
                'balance_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                
                // Items List
                'items_list' => $invoice->items->map(function ($item) {
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
                
                // Payment Information
                'payments' => $invoice->transactions->map(function ($transaction) {
                    $attachmentService = new CommonAttachmentService();
                    $attachments = $attachmentService->getAttachments('Transaction', $transaction->transaction_id);
                    return [
                        'id' => $transaction->payment_id ?? null,
                        'date' => $transaction->payment_date,
                        'amount' => $transaction->payment_amount,
                        'method' => $transaction->payment_method ?? 'N/A',
                        'reference' => $transaction->reference_number ?? 'N/A',
                        'notes' => $transaction->notes ?? 'N/A',
                        'attachments' => $attachments,
                    ];
                }),
                'payment_summary' => [
                    'total_paid' => $invoice->invoice_paid_amount,
                    'total_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                    'payment_count' => $invoice->transactions->count(),
                    'last_payment_date' => $invoice->transactions->max('payment_date') ?? null,
                ],
                
                // Messages and Notes
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'message_on_invoice' => $invoice->notes ?? 'N/A',
                'message_on_statement' => $invoice->terms ?? 'N/A',
                
                // Attachments
                'attachments' => $invoice->attachments()->get()->map(function($attachment) {
                    return [
                        'id' => $attachment->id,
                        'path' => $attachment->image_url,
                        'created_at' => $attachment->created_at
                    ];
                }),
                'uploaded_attachments' => $uploadedFilesInfo,
                
                // Timestamps
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedInvoice,
                'message' => 'Invoice created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified invoice.
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

            $invoice = Invoice::with(['contact', 'items', 'paymentTerm', 'transactions', 'attachments'])
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            // Transform the data to include all available fields
            $transformedInvoice = [
                // Basic Invoice Information
                'invoice_id' => $invoice->invoice_id,
                'invoice_date' => $invoice->invoice_date,
                'invoice_due_date' => $invoice->invoice_due_date,
                'invoice_status' => $invoice->invoice_status,
                'invoice_payment_status' => $invoice->invoice_payment_status,
                'invoice_amount' => $invoice->invoice_amount,
                'invoice_total' => $invoice->invoice_total,
                'invoice_paid_amount' => $invoice->invoice_paid_amount,
                // invoice_attachment field removed - use attachments table instead
                
                // Company Information
                'company_id' => $invoice->company_id,
                
                // Customer Information
                'customer' => [
                    'id' => $invoice->contact->contact_id ?? null,
                    'name' => $this->getCustomerName($invoice->contact),
                    'email' => $invoice->contact->contact_email_address ?? 'N/A',
                    'phone' => $invoice->contact->contact_phone ?? 'N/A',
                    'type' => $invoice->contact->contact_type ?? 'N/A',
                    'company_name' => $invoice->contact->contact_company_name ?? 'N/A',
                ],
                'sent_to' => $invoice->sent_to,
                
                // Billing Address
                'billing_address' => $this->getBillingAddress($invoice->contact),
                
                // Payment Terms
                'payment_term_id' => $invoice->payment_term_id,
                'terms' => [
                    'id' => $invoice->paymentTerm->id ?? null,
                    'description' => $invoice->paymentTerm->description ?? 'N/A',
                ],
                
                // Financial Details
                'subtotal' => $invoice->subtotal ?? $invoice->invoice_amount,
                'discount' => [
                    'type' => $invoice->discount_type ?? 0,
                    'method' => $invoice->discount_method ?? 'N/A',
                    'amount' => $invoice->discount_amount ?? 0,
                    'percentage' => $invoice->discount_type ?? 0,
                ],
                'tax' => [
                    'rate' => $invoice->tax_rate ?? 0,
                    'amount' => $invoice->tax_amount ?? 0,
                ],
                'gst_amount' => $invoice->gst_amount ?? 0,
                'total' => $invoice->invoice_total,
                'balance_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                
                // Items List
                'items_list' => $invoice->items->map(function ($item) {
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
                
                // Payment Information
                'payments' => $invoice->transactions->map(function ($transaction) {
                    $attachmentService = new CommonAttachmentService();
                    $attachments = $attachmentService->getAttachments('Transaction', $transaction->transaction_id);
                    return [
                        'id' => $transaction->payment_id ?? null,
                        'date' => $transaction->payment_date,
                        'amount' => $transaction->payment_amount,
                        'method' => $transaction->payment_method ?? 'N/A',
                        'reference' => $transaction->reference_number ?? 'N/A',
                        'notes' => $transaction->notes ?? 'N/A',
                        'attachments' => $attachments,
                    ];
                }),
                'payment_summary' => [
                    'total_paid' => $invoice->invoice_paid_amount,
                    'total_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                    'payment_count' => $invoice->transactions->count(),
                    'last_payment_date' => $invoice->transactions->max('payment_date') ?? null,
                ],
                
                // Messages and Notes
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'message_on_invoice' => $invoice->notes ?? 'N/A',
                'message_on_statement' => $invoice->terms ?? 'N/A',
                
                // Attachments
                'attachments' => $invoice->attachments->map(function($attachment) {
                    return [
                        'id' => $attachment->id,
                        'path' => $attachment->image_url,
                        'created_at' => $attachment->created_at
                    ];
                }),
                
                // Timestamps
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedInvoice,
                'message' => 'Invoice retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }
    }

    /**
     * Update the specified invoice.
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

            $invoice = Invoice::where('company_id', $user->company_id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                
                'sent_to' => 'sometimes|required|exists:contacts,contact_id',
                'invoice_date' => 'sometimes|required|date',
                'invoice_due_date' => 'sometimes|required|date|after:invoice_date',
                'invoice_status' => 'sometimes|required|in:Due,Past Due,Draft,Sent,Viewed,Approved,Rejected,Cancelled',
                'invoice_payment_status' => 'sometimes|required|in:Paid,Unpaid,Partially Paid,Overdue',
                'invoice_paid_amount' => 'sometimes|required|numeric|min:0',
                'discount_type' => 'nullable|numeric|min:0|max:100',
                'discount_method' => 'nullable|in:percentage,fixed',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_rate' => 'nullable|numeric|min:0|max:100',
                // invoice_attachment field removed - use attachments table instead
                'items' => 'sometimes|required|array|min:1',
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

            // Update invoice basic info
            $invoice->update($request->only([
                'company_id', 'sent_to', 'invoice_date', 'invoice_due_date',
                'invoice_status', 'invoice_payment_status', 'invoice_paid_amount',
                'discount_type', 'discount_method', 'discount_amount',
                'tax_rate', 'tax_amount'
                // invoice_attachment field removed - use attachments table instead
            ]));

            // Update items if provided
            if ($request->has('items')) {
                // Delete existing items
                $invoice->items()->delete();

                // Calculate new totals
                $invoiceAmount = 0;
                $totalGstAmount = 0;
                
                foreach ($request->items as $item) {
                    $itemCost = $item['item_quantity'] * $item['item_rate'];
                    $invoiceAmount += $itemCost;

                    $gstRate = $item['item_gst_rate'] ?? 10;
                    $itemGst = $itemCost * ($gstRate / 100);
                    $itemTotal = $itemCost + $itemGst;
                    $totalGstAmount += $itemGst;

                    InvoiceItem::create([
                        'invoice_id' => $invoice->invoice_id,
                        'item_description' => $item['item_description'],
                        'item_quantity' => $item['item_quantity'],
                        'item_rate' => $item['item_rate'],
                        'item_cost' => $itemCost,
                        'item_gst' => $itemGst,
                        'item_total' => $itemTotal,
                    ]);
                }

                // Calculate discount
                $discountAmount = 0;
                if ($request->has('discount_type') && $request->discount_type > 0) {
                    if ($request->discount_method === 'percentage') {
                        $discountAmount = $invoiceAmount * ($request->discount_type / 100);
                    } else {
                        $discountAmount = $request->discount_type; // fixed amount
                    }
                }

                // Calculate subtotal (after discount)
                $subtotal = $invoiceAmount - $discountAmount;

                // Calculate tax (separate from GST)
                $taxAmount = 0;
                if ($request->has('tax_rate') && $request->tax_rate > 0) {
                    $taxAmount = $subtotal * ($request->tax_rate / 100);
                }

                // Calculate final total
                $invoiceTotal = $subtotal + $taxAmount + $totalGstAmount;

                $invoice->update([
                    'invoice_amount' => $invoiceAmount,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $subtotal,
                    'gst_amount' => $totalGstAmount,
                    'invoice_total' => $invoiceTotal,
                ]);
            }

            // Handle file attachments if provided
            $uploadedFilesInfo = [];
            if ($request->hasFile('attachments')) {
                $attachmentService = new CommonAttachmentService();
                $uploadedFilesInfo = $attachmentService->storeMultipleAttachments(
                    'Invoice',
                    (int) $invoice->company_id,
                    (int) $invoice->invoice_id,
                    $request->file('attachments')
                );

                foreach ($uploadedFilesInfo as $fileInfo) {
                    $attachment = Attachment::find($fileInfo['id']);
                    if ($attachment) {
                        AuditLogService::logAction(
                            $invoice,
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
            $invoice->load(['company', 'contact', 'items', 'paymentTerm', 'transactions', 'attachments']);

            // Transform the data to include all available fields (same as show method)
            $transformedInvoice = [
                // Basic Invoice Information
                'invoice_id' => $invoice->invoice_id,
                'invoice_date' => $invoice->invoice_date,
                'invoice_due_date' => $invoice->invoice_due_date,
                'invoice_status' => $invoice->invoice_status,
                'invoice_payment_status' => $invoice->invoice_payment_status,
                'invoice_amount' => $invoice->invoice_amount,
                'invoice_total' => $invoice->invoice_total,
                'invoice_paid_amount' => $invoice->invoice_paid_amount,
                // invoice_attachment field removed - use attachments table instead
                
                // Company Information
                'company_id' => $invoice->company_id,
                
                // Customer Information
                'customer' => [
                    'id' => $invoice->contact->contact_id ?? null,
                    'name' => $this->getCustomerName($invoice->contact),
                    'email' => $invoice->contact->contact_email_address ?? 'N/A',
                    'phone' => $invoice->contact->contact_phone ?? 'N/A',
                    'type' => $invoice->contact->contact_type ?? 'N/A',
                    'company_name' => $invoice->contact->contact_company_name ?? 'N/A',
                ],
                'sent_to' => $invoice->sent_to,
                
                // Billing Address
                'billing_address' => $this->getBillingAddress($invoice->contact),
                
                // Payment Terms
                'payment_term_id' => $invoice->payment_term_id,
                'terms' => [
                    'id' => $invoice->paymentTerm->id ?? null,
                    'description' => $invoice->paymentTerm->description ?? 'N/A',
                ],
                
                // Financial Details
                'subtotal' => $invoice->subtotal ?? $invoice->invoice_amount,
                'discount' => [
                    'type' => $invoice->discount_type ?? 0,
                    'method' => $invoice->discount_method ?? 'N/A',
                    'amount' => $invoice->discount_amount ?? 0,
                    'percentage' => $invoice->discount_type ?? 0,
                ],
                'tax' => [
                    'rate' => $invoice->tax_rate ?? 0,
                    'amount' => $invoice->tax_amount ?? 0,
                ],
                'gst_amount' => $invoice->gst_amount ?? 0,
                'total' => $invoice->invoice_total,
                'balance_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                
                // Items List
                'items_list' => $invoice->items->map(function ($item) {
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
                
                // Payment Information
                'payments' => $invoice->transactions->map(function ($transaction) {
                    $attachmentService = new CommonAttachmentService();
                    $attachments = $attachmentService->getAttachments('Transaction', $transaction->transaction_id);
                    return [
                        'id' => $transaction->payment_id ?? null,
                        'date' => $transaction->payment_date,
                        'amount' => $transaction->payment_amount,
                        'method' => $transaction->payment_method ?? 'N/A',
                        'reference' => $transaction->reference_number ?? 'N/A',
                        'notes' => $transaction->notes ?? 'N/A',
                        'attachments' => $attachments,
                    ];
                }),
                'payment_summary' => [
                    'total_paid' => $invoice->invoice_paid_amount,
                    'total_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                    'payment_count' => $invoice->transactions->count(),
                    'last_payment_date' => $invoice->transactions->max('payment_date') ?? null,
                ],
                
                // Messages and Notes
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'message_on_invoice' => $invoice->notes ?? 'N/A',
                'message_on_statement' => $invoice->terms ?? 'N/A',
                
                // Attachments
                'attachments' => $invoice->attachments()->get()->map(function($attachment) {
                    return [
                        'id' => $attachment->id,
                        'path' => $attachment->image_url,
                        'created_at' => $attachment->created_at
                    ];
                }),
                'uploaded_attachments' => $uploadedFilesInfo,
                
                // Timestamps
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedInvoice,
                'message' => 'Invoice updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified invoice.
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

            $invoice = Invoice::where('company_id', $user->company_id)->findOrFail($id);

            // Check if invoice has transactions
            if ($invoice->transactions()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete invoice with existing transactions'
                ], 422);
            }

            // Delete invoice items (cascade will handle this)
            $invoice->items()->delete();
            
            // Delete the invoice
            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice statistics.
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
                'total_invoices' => Invoice::where('company_id', $user->company_id)->count(),
                'total_amount' => Invoice::where('company_id', $user->company_id)->sum('invoice_total'),
                'paid_amount' => Invoice::where('company_id', $user->company_id)->sum('invoice_paid_amount'),
                'unpaid_amount' => Invoice::where('company_id', $user->company_id)->sum('invoice_total') - Invoice::where('company_id', $user->company_id)->sum('invoice_paid_amount'),
                'due_invoices' => Invoice::where('company_id', $user->company_id)->where('invoice_status', 'Due')->count(),
                'past_due_invoices' => Invoice::where('company_id', $user->company_id)->where('invoice_status', 'Past Due')->count(),
                'paid_invoices' => Invoice::where('company_id', $user->company_id)->where('invoice_payment_status', 'Paid')->count(),
                'unpaid_invoices' => Invoice::where('company_id', $user->company_id)->where('invoice_payment_status', 'Unpaid')->count(),
                'partially_paid_invoices' => Invoice::where('company_id', $user->company_id)->where('invoice_payment_status', 'Partially Paid')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get companies and contacts for dropdowns.
     */
    public function getFormData(): JsonResponse
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

            $companies = Company::where('company_id', $user->company_id)->select('company_id', 'company_name')->get();
            $contacts = Contact::where('company_id', $user->company_id)->select('contact_id', 'contact_name', 'contact_email')->get();
            $taxRates = TaxRate::active()->select('id', 'tax_name', 'rate', 'type')->get();
            $paymentTerms = PaymentTerm::active()->select('id', 'name', 'due_calculation_method', 'fixed_days_value', 'certain_day_value')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'companies' => $companies,
                    'contacts' => $contacts,
                    'tax_rates' => $taxRates,
                    'payment_terms' => $paymentTerms,
                ],
                'message' => 'Form data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving form data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice items for a specific invoice.
     */
    public function getInvoiceItems($invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $items = $invoice->items()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => [
                        'id' => $invoice->invoice_id,
                        'total_amount' => $invoice->invoice_total,
                        'status' => $invoice->invoice_status,
                    ],
                    'items' => $items,
                    'summary' => [
                        'total_items' => $items->count(),
                        'subtotal' => $items->sum('item_cost'),
                        'total_gst' => $items->sum('item_gst'),
                        'grand_total' => $items->sum('item_total'),
                    ]
                ],
                'message' => 'Invoice items retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoice items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice transactions for a specific invoice.
     */
    public function getInvoiceTransactions($invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $transactions = $invoice->transactions()->get();

            // Transform transactions to include attachment details
            $transformedTransactions = $transactions->map(function ($transaction) {
                $transactionData = $transaction->toArray();
                
                // Fetch attachments from attachments table only
                $attachmentService = new CommonAttachmentService();
                $tableAttachments = $attachmentService->getAttachments('Transaction', $transaction->transaction_id);
                
                $transactionData['attachments'] = $tableAttachments;
                $transactionData['attachment_count'] = count($tableAttachments);
                
                return $transactionData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => [
                        'id' => $invoice->invoice_id,
                        'total_amount' => $invoice->invoice_total,
                        'paid_amount' => $invoice->invoice_paid_amount,
                        'balance_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                    ],
                    'transactions' => $transformedTransactions,
                    'summary' => [
                        'total_transactions' => $transactions->count(),
                        'total_paid' => $transactions->sum('payment_amount'),
                        'balance_due' => $invoice->invoice_total - $transactions->sum('payment_amount'),
                        'total_attachments' => $transactions->sum(function ($transaction) {
                            return count($transaction->attachments ?? []);
                        }),
                    ]
                ],
                'message' => 'Invoice transactions retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoice transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a transaction to an invoice.
     */
    public function addTransaction(Request $request, $invoiceId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'payment_method' => 'nullable|string|max:100',
                'reference_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'attachments.*' => 'nullable|file|max:20480', // 20MB max for attachments
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $invoice = Invoice::findOrFail($invoiceId);
            
            // Check if payment amount exceeds balance
            $currentBalance = $invoice->invoice_total - $invoice->invoice_paid_amount;
            if ($request->payment_amount > $currentBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount cannot exceed balance due',
                    'data' => [
                        'balance_due' => $currentBalance,
                        'payment_amount' => $request->payment_amount
                    ]
                ], 422);
            }

            DB::beginTransaction();

            // Create transaction
            $transaction = $invoice->transactions()->create([
                'payment_amount' => $request->payment_amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'attachments' => [], // Initialize empty attachments array
            ]);

            // Handle file attachments if provided
            $uploadedAttachments = [];
            if ($request->hasFile('attachments')) {
                $attachmentService = new CommonAttachmentService();
                foreach ($request->file('attachments') as $file) {
                    if ($attachmentService->validateFile($file)) {
                        $fileInfo = $attachmentService->storeAttachment(
                            'Transaction', 
                            $invoice->company_id, 
                            $invoice->invoice_id, 
                            $file, 
                            $transaction->transaction_id
                        );
                        if ($fileInfo) {
                            // Create record in the new attachments table
                            $attachment = Attachment::create([
                                'type' => 'Transaction',
                                'type_id' => $transaction->transaction_id,
                                'image_url' => $fileInfo['path'], // This now includes "storage/" prefix
                                'created_by' => Auth::id(), // Assuming authenticated user
                                'updated_by' => Auth::id(),
                            ]);
                            AuditLogService::logAction(
                                $transaction,
                                'attachment_created',
                                [],
                                [
                                    'attachment_id' => $attachment->id,
                                    'path' => $attachment->image_url,
                                ]
                            );
                            $uploadedAttachments[] = $attachment->toArray();
                        }
                    }
                }
            }

            // Update invoice paid amount
            $newPaidAmount = $invoice->invoice_paid_amount + $request->payment_amount;
            $newPaymentStatus = $newPaidAmount >= $invoice->invoice_total ? 'Paid' : 'Partially Paid';
            
            $invoice->update([
                'invoice_paid_amount' => $newPaidAmount,
                'invoice_payment_status' => $newPaymentStatus,
            ]);

            DB::commit();

            // Load relationships to get updated data
            $invoice->load(['company', 'contact', 'items', 'paymentTerm', 'transactions']);

            // Transform the data to include all available fields (same as show method)
            $transformedInvoice = [
                // Basic Invoice Information
                'invoice_id' => $invoice->invoice_id,
                'invoice_date' => $invoice->invoice_date,
                'invoice_due_date' => $invoice->invoice_due_date,
                'invoice_status' => $invoice->invoice_status,
                'invoice_payment_status' => $invoice->invoice_payment_status,
                'invoice_amount' => $invoice->invoice_amount,
                'invoice_total' => $invoice->invoice_total,
                'invoice_paid_amount' => $invoice->invoice_paid_amount,
                // invoice_attachment field removed - use attachments table instead
                
                // Company Information
                'company_id' => $invoice->company_id,
                
                // Customer Information
                'customer' => [
                    'id' => $invoice->contact->contact_id ?? null,
                    'name' => $this->getCustomerName($invoice->contact),
                    'email' => $invoice->contact->contact_email_address ?? 'N/A',
                    'phone' => $invoice->contact->contact_phone ?? 'N/A',
                    'type' => $invoice->contact->contact_type ?? 'N/A',
                    'company_name' => $invoice->contact->contact_company_name ?? 'N/A',
                ],
                'sent_to' => $invoice->sent_to,
                
                // Billing Address
                'billing_address' => $this->getBillingAddress($invoice->contact),
                
                // Payment Terms
                'payment_term_id' => $invoice->payment_term_id,
                'terms' => [
                    'id' => $invoice->paymentTerm->id ?? null,
                    'description' => $invoice->paymentTerm->description ?? 'N/A',
                ],
                
                // Financial Details
                'subtotal' => $invoice->subtotal ?? $invoice->invoice_amount,
                'discount' => [
                    'type' => $invoice->discount_type ?? 0,
                    'method' => $invoice->discount_method ?? 'N/A',
                    'amount' => $invoice->discount_amount ?? 0,
                    'percentage' => $invoice->discount_type ?? 0,
                ],
                'tax' => [
                    'rate' => $invoice->tax_rate ?? 0,
                    'amount' => $invoice->tax_amount ?? 0,
                ],
                'gst_amount' => $invoice->gst_amount ?? 0,
                'total' => $invoice->invoice_total,
                'balance_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                
                // Items List
                'items_list' => $invoice->items->map(function ($item) {
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
                
                // Payment Information
                'payments' => $invoice->transactions->map(function ($transaction) {
                    $attachmentService = new CommonAttachmentService();
                    $attachments = $attachmentService->getAttachments('Transaction', $transaction->transaction_id);
                    return [
                        'id' => $transaction->payment_id ?? null,
                        'date' => $transaction->payment_date,
                        'amount' => $transaction->payment_amount,
                        'method' => $transaction->payment_method ?? 'N/A',
                        'reference' => $transaction->reference_number ?? 'N/A',
                        'notes' => $transaction->notes ?? 'N/A',
                        'attachments' => $attachments,
                    ];
                }),
                'payment_summary' => [
                    'total_paid' => $invoice->invoice_paid_amount,
                    'total_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                    'payment_count' => $invoice->transactions->count(),
                    'last_payment_date' => $invoice->transactions->max('payment_date') ?? null,
                ],
                
                // Messages and Notes
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'message_on_invoice' => $invoice->notes ?? 'N/A',
                'message_on_statement' => $invoice->terms ?? 'N/A',
                
                // Attachments
                'attachments' => $invoice->attachments()->get()->map(function($attachment) {
                    return [
                        'id' => $attachment->id,
                        'path' => $attachment->image_url,
                        'created_at' => $attachment->created_at
                    ];
                }),
                'uploaded_attachments' => $uploadedAttachments,
                
                // Timestamps
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction' => array_merge($transaction->toArray(), [
                        'attachments' => $uploadedAttachments
                    ]),
                    'invoice' => $transformedInvoice
                ],
                'message' => 'Transaction added successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error adding transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoices by status with detailed information.
     */
    public function getInvoicesByStatus(Request $request, $status): JsonResponse
    {
        try {
            $query = Invoice::with(['company', 'contact', 'items', 'transactions'])
                ->where('invoice_status', $status);

            if ($request->has('payment_status')) {
                $query->where('invoice_payment_status', $request->payment_status);
            }

            $invoices = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'message' => "Invoices with status '{$status}' retrieved successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue invoices.
     */
    public function getOverdueInvoices(Request $request): JsonResponse
    {
        try {
            $query = Invoice::with(['company', 'contact', 'items'])
                ->where('invoice_due_date', '<', now())
                ->where('invoice_payment_status', '!=', 'Paid');

            $invoices = $query->paginate($request->get('per_page', 15));

            $summary = [
                'total_overdue' => $invoices->total(),
                'total_amount_overdue' => $invoices->getCollection()->sum('invoice_total'),
                'total_paid_amount' => $invoices->getCollection()->sum('invoice_paid_amount'),
                'total_balance_due' => $invoices->getCollection()->sum('invoice_total') - $invoices->getCollection()->sum('invoice_paid_amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'summary' => $summary,
                'message' => 'Overdue invoices retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving overdue invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update discount for an invoice.
     */
    public function updateDiscount(Request $request, $invoiceId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'discount_type' => 'required|numeric|min:0|max:100',
                'discount_method' => 'required|in:percentage,fixed',
                'discount_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $invoice = Invoice::findOrFail($invoiceId);
            
            DB::beginTransaction();

            // Calculate new discount amount
            $discountAmount = 0;
            if ($request->discount_type > 0) {
                if ($request->discount_method === 'percentage') {
                    $discountAmount = $invoice->invoice_amount * ($request->discount_type / 100);
                } else {
                    $discountAmount = $request->discount_type; // fixed amount
                }
            }

            // Calculate new subtotal and total
            $subtotal = $invoice->invoice_amount - $discountAmount;
            $newTotal = $subtotal + $invoice->tax_amount + $invoice->gst_amount;

            // Update invoice
            $invoice->update([
                'discount_type' => $request->discount_type,
                'discount_method' => $request->discount_method,
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
                'invoice_total' => $newTotal,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => $invoice->fresh(),
                    'calculations' => [
                        'original_amount' => $invoice->invoice_amount,
                        'discount_amount' => $discountAmount,
                        'subtotal' => $subtotal,
                        'tax_amount' => $invoice->tax_amount,
                        'gst_amount' => $invoice->gst_amount,
                        'new_total' => $newTotal,
                    ]
                ],
                'message' => 'Discount updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating discount: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update tax for an invoice.
     */
    public function updateTax(Request $request, $invoiceId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tax_rate' => 'required|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $invoice = Invoice::findOrFail($invoiceId);
            
            DB::beginTransaction();

            // Calculate new tax amount
            $taxAmount = $invoice->subtotal * ($request->tax_rate / 100);
            $newTotal = $invoice->subtotal + $taxAmount + $invoice->gst_amount;

            // Update invoice
            $invoice->update([
                'tax_rate' => $request->tax_rate,
                'tax_amount' => $taxAmount,
                'invoice_total' => $newTotal,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => $invoice->fresh(),
                    'calculations' => [
                        'subtotal' => $invoice->subtotal,
                        'tax_rate' => $request->tax_rate,
                        'tax_amount' => $taxAmount,
                        'gst_amount' => $invoice->gst_amount,
                        'new_total' => $newTotal,
                    ]
                ],
                'message' => 'Tax updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating tax: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice summary with detailed breakdown.
     */
    public function getInvoiceSummary($invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::with(['company', 'contact', 'items'])->findOrFail($invoiceId);

            $summary = [
                'invoice_id' => $invoice->invoice_id,
                'company' => $invoice->company->company_name ?? 'N/A',
                'contact' => $invoice->contact->contact_name ?? 'N/A',
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->invoice_due_date,
                'status' => $invoice->invoice_status,
                'payment_status' => $invoice->invoice_payment_status,
                'breakdown' => [
                    'items_total' => $invoice->invoice_amount,
                    'discount' => [
                        'type' => $invoice->discount_type,
                        'method' => $invoice->discount_method,
                        'amount' => $invoice->discount_amount,
                    ],
                    'subtotal' => $invoice->subtotal,
                    'tax' => [
                        'rate' => $invoice->tax_rate,
                        'amount' => $invoice->tax_amount,
                    ],
                    'gst' => $invoice->gst_amount,
                    'total' => $invoice->invoice_total,
                    'paid' => $invoice->invoice_paid_amount,
                    'balance_due' => $invoice->invoice_total - $invoice->invoice_paid_amount,
                ],
                'items_count' => $invoice->items->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
                'message' => 'Invoice summary retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoice summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export invoices to PDF format
     */
    public function exportToPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        try {
            // Select only the required fields for the list view
            $query = Invoice::select([
                'invoices.invoice_id',
                'invoices.invoice_date',
                'invoices.invoice_total',
                'invoices.invoice_status',
                'invoices.invoice_payment_status',
                'invoices.invoice_due_date',
                'invoices.sent_to'
            ])
            ->with(['contact:contact_id,contact_first_name,contact_last_name,contact_company_name,contact_type']);

            // Apply filters (same as index method)
            if ($request->has('status')) {
                $query->where('invoice_status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('invoice_payment_status', $request->payment_status);
            }

            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('contact_id')) {
                $query->where('sent_to', $request->contact_id);
            }

            if ($request->has('date_from')) {
                $query->where('invoice_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('invoice_date', '<=', $request->date_to);
            }

            if ($request->has('due_date_from')) {
                $query->where('invoice_due_date', '>=', $request->due_date_from);
            }

            if ($request->has('due_date_to')) {
                $query->where('invoice_due_date', '<=', $request->due_date_to);
            }

            if ($request->has('min_amount')) {
                $query->where('invoice_total', '>=', $request->min_amount);
            }

            if ($request->has('max_amount')) {
                $query->where('invoice_total', '<=', $request->max_amount);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->whereHas('contact', function($q) use ($search) {
                        $q->where('contact_first_name', 'like', "%{$search}%")
                          ->orWhere('contact_last_name', 'like', "%{$search}%")
                          ->orWhere('contact_company_name', 'like', "%{$search}%")
                          ->orWhere('contact_email_address', 'like', "%{$search}%");
                    });
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'invoice_date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Get all invoices (no pagination for PDF)
            $invoices = $query->get();

            // Transform the data to include only required fields
            $transformedInvoices = $invoices->map(function ($invoice) {
                $statusWithDays = $this->getInvoiceStatusWithDays($invoice);
                $daysInfo = $this->getDaysInfo($invoice);
                
                return [
                    'id' => $invoice->invoice_id,
                    'invoice_date' => $invoice->invoice_date,
                    'customer_name' => $this->getCustomerName($invoice->contact),
                    'total_amount' => $invoice->invoice_total,
                    'status' => $statusWithDays,
                    'payment_status' => $invoice->invoice_payment_status,
                    'due_date' => $invoice->invoice_due_date,
                    'is_overdue' => $this->isInvoiceOverdue($invoice),
                    'days_info' => $daysInfo
                ];
            });

            // Calculate summary data
            $summary = [
                'total_count' => $invoices->count(),
                'total_amount' => $invoices->sum('invoice_total'),
                'status_distribution' => [
                    'due_today' => $invoices->filter(function($invoice) {
                        return $this->getInvoiceStatusWithDays($invoice) === 'Due Today';
                    })->count(),
                    'due_tomorrow' => $invoices->filter(function($invoice) {
                        return $this->getInvoiceStatusWithDays($invoice) === 'Due Tomorrow';
                    })->count(),
                    'due_future' => $invoices->filter(function($invoice) {
                        $status = $this->getInvoiceStatusWithDays($invoice);
                        return strpos($status, 'Due in') === 0;
                    })->count(),
                    'overdue' => $invoices->filter(function($invoice) {
                        return $this->isInvoiceOverdue($invoice);
                    })->count(),
                ],
                'payment_distribution' => [
                    'paid' => $invoices->where('invoice_payment_status', 'Paid')->count(),
                    'unpaid' => $invoices->where('invoice_payment_status', 'Unpaid')->count(),
                    'partially_paid' => $invoices->where('invoice_payment_status', 'Partially Paid')->count(),
                ]
            ];

            // Generate PDF
            $pdf = PDF::loadView('pdfs.invoices', [
                'invoices' => $transformedInvoices,
                'summary' => $summary,
                'filters' => $request->all(),
                'generated_at' => now()->format('Y-m-d H:i:s')
            ]);

            // Set PDF options
            $pdf->setPaper('A4', 'landscape');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);

            // Generate filename
            $filename = 'invoices_' . now()->format('Y-m-d_H-i-s') . '.pdf';

            // Return PDF as download
            return $pdf->download($filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', now()->year);
            $month = $request->get('month', now()->month);

            // Monthly invoice amounts for the specified year
            $monthlyData = $this->getMonthlyInvoiceAmounts($year);
            
            // Recent invoices with customer details
            $recentInvoices = $this->getRecentInvoices();
            
            // Overdue invoices - get first 10 from the existing method
            $overdueResponse = $this->getOverdueInvoices(new Request(['per_page' => 10]));
            $overdueInvoices = $overdueResponse->getData()->data->data ?? [];
            
            // Payment status pie chart data
            $paymentStatusData = $this->getPaymentStatusData();
            
            // Summary statistics
            $summary = $this->getDashboardSummary();

            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_data' => $monthlyData,
                    'recent_invoices' => $recentInvoices,
                    'overdue_invoices' => $overdueInvoices,
                    'payment_status_chart' => $paymentStatusData,
                    'summary' => $summary
                ],
                'message' => 'Dashboard data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly invoice amounts for a specific year.
     */
    private function getMonthlyInvoiceAmounts(int $year): array
    {
        $monthlyData = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $startDate = "{$year}-{$month}-01";
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $total = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
                           ->sum('invoice_total');
            
            $monthlyData[] = [
                'month' => date('M', strtotime($startDate)),
                'month_number' => $month,
                'year' => $year,
                'total_amount' => $total,
                'formatted_amount' => '$' . number_format($total, 2),
                'invoice_count' => Invoice::whereBetween('invoice_date', [$startDate, $endDate])->count()
            ];
        }
        
        return $monthlyData;
    }

    /**
     * Get recent invoices with customer details.
     */
    private function getRecentInvoices(): array
    {
        return Invoice::select([
                'invoices.invoice_id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.invoice_due_date',
                'invoices.invoice_total',
                'invoices.invoice_payment_status',
                'invoices.sent_to'
            ])
            ->with(['contact:contact_id,contact_first_name,contact_last_name,contact_company_name'])
            ->orderBy('invoice_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->contact ? 
                        ($invoice->contact->contact_company_name ?: 
                         $invoice->contact->contact_first_name . ' ' . $invoice->contact->contact_last_name) : 
                        'Unknown Customer',
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->invoice_due_date?->format('Y-m-d'),
                    'total_amount' => $invoice->invoice_total,
                    'formatted_amount' => '$' . number_format($invoice->invoice_total, 2),
                    'payment_status' => $invoice->invoice_payment_status,
                    'is_overdue' => $invoice->invoice_due_date && $invoice->invoice_due_date < now() && 
                                   $invoice->invoice_payment_status !== 'Paid'
                ];
            })
            ->toArray();
    }

    /**
     * Get payment status data for pie chart.
     */
    private function getPaymentStatusData(): array
    {
        $statuses = ['Paid', 'Unpaid', 'Partially Paid', 'Overdue'];
        $data = [];
        
        foreach ($statuses as $status) {
            $count = Invoice::where('invoice_payment_status', $status)->count();
            $amount = Invoice::where('invoice_payment_status', $status)->sum('invoice_total');
            
            $data[] = [
                'status' => $status,
                'count' => $count,
                'amount' => $amount,
                'formatted_amount' => '$' . number_format($amount, 2),
                'percentage' => Invoice::count() > 0 ? round(($count / Invoice::count()) * 100, 1) : 0
            ];
        }
        
        return $data;
    }

    /**
     * Get dashboard summary statistics.
     */
    private function getDashboardSummary(): array
    {
        $totalInvoices = Invoice::count();
        $totalAmount = Invoice::sum('invoice_total');
        $paidAmount = Invoice::where('invoice_payment_status', 'Paid')->sum('invoice_total');
        $unpaidAmount = Invoice::where('invoice_payment_status', '!=', 'Paid')->sum('invoice_total');
        $overdueAmount = Invoice::where('invoice_due_date', '<', now())
                               ->where('invoice_payment_status', '!=', 'Paid')
                               ->sum('invoice_total');
        
        return [
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'formatted_total_amount' => '$' . number_format($totalAmount, 2),
            'paid_amount' => $paidAmount,
            'formatted_paid_amount' => '$' . number_format($paidAmount, 2),
            'unpaid_amount' => $unpaidAmount,
            'formatted_unpaid_amount' => '$' . number_format($unpaidAmount, 2),
            'overdue_amount' => $overdueAmount,
            'formatted_overdue_amount' => '$' . number_format($overdueAmount, 2),
            'paid_percentage' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
            'unpaid_percentage' => $totalAmount > 0 ? round(($unpaidAmount / $totalAmount) * 100, 1) : 0
        ];
    }

    /**
     * Upload attachments for an invoice
     */
    public function uploadAttachments(Request $request, $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            
            $request->validate([
                'attachments.*' => 'required|file|max:20480', // 20MB max
            ]);

            $attachmentService = new CommonAttachmentService();
            $uploadedFilesInfo = $attachmentService->storeMultipleAttachments(
                'Invoice', 
                $invoice->company_id, 
                $invoice->invoice_id, 
                $request->file('attachments')
            );

            // Log audit for each uploaded attachment
            foreach ($uploadedFilesInfo as $fileInfo) {
                $attachment = Attachment::find($fileInfo['id']);
                if ($attachment) {
                    AuditLogService::logAction(
                        $invoice,
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
            \Log::error('Failed to upload invoice attachments', [
                'invoice_id' => $invoiceId,
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
     * Get invoice attachments
     */
    public function getAttachments($invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $attachmentService = new CommonAttachmentService();
            
            $attachments = $attachmentService->getAttachments('Invoice', $invoice->invoice_id);
            
            return response()->json([
                'success' => true,
                'data' => $attachments,
                'message' => 'Invoice attachments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to get invoice attachments', [
                'invoice_id' => $invoiceId,
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
     * Delete invoice attachment
     */
    public function deleteAttachment(Request $request, $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $attachmentService = new CommonAttachmentService();
            $attachmentId = $request->attachment_id;

            if (!$attachmentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment ID is required'
                ], 400);
            }

            $success = $attachmentService->deleteAttachment('Invoice', $invoice->invoice_id, $attachmentId);

            if ($success) {
                AuditLogService::logAction(
                    $invoice,
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
            \Log::error('Failed to delete invoice attachment', [
                'invoice_id' => $invoiceId,
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

}
