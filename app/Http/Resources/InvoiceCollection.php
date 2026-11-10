<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InvoiceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'pagination' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
                'has_more_pages' => $this->hasMorePages(),
            ],
            'summary' => [
                'total_count' => $this->total(),
                'total_amount' => $this->collection->sum('invoice_total'),
                'total_paid' => $this->collection->sum('invoice_paid_amount'),
                'total_outstanding' => $this->collection->sum('invoice_total') - $this->collection->sum('invoice_paid_amount'),
                'status_distribution' => [
                    'draft' => $this->collection->where('invoice_status', 'Draft')->count(),
                    'sent' => $this->collection->where('invoice_status', 'Sent')->count(),
                    'viewed' => $this->collection->where('invoice_status', 'Viewed')->count(),
                    'due' => $this->collection->where('invoice_status', 'Due')->count(),
                    'past_due' => $this->collection->where('invoice_status', 'Past Due')->count(),
                    'approved' => $this->collection->where('invoice_status', 'Approved')->count(),
                    'rejected' => $this->collection->where('invoice_status', 'Rejected')->count(),
                    'cancelled' => $this->collection->where('invoice_status', 'Cancelled')->count(),
                ],
                'payment_distribution' => [
                    'unpaid' => $this->collection->where('invoice_payment_status', 'Unpaid')->count(),
                    'partially_paid' => $this->collection->where('invoice_payment_status', 'Partially Paid')->count(),
                    'paid' => $this->collection->where('invoice_payment_status', 'Paid')->count(),
                    'overdue' => $this->collection->where('invoice_payment_status', 'Overdue')->count(),
                ],
            ],
            'meta' => [
                'success' => true,
                'message' => 'Invoices retrieved successfully',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
