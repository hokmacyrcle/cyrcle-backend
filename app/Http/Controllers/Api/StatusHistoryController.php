<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatusHistoryController extends Controller
{
    protected $statusHistoryService;

    public function __construct(StatusHistoryService $statusHistoryService)
    {
        $this->statusHistoryService = $statusHistoryService;
    }

    /**
     * Get all status history with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $type = $request->get('type'); // 'Quotes' or 'Invoice'
            $status = $request->get('status');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            $statusHistory = $this->statusHistoryService->getStatusHistory(
                $type, 
                $status, 
                $dateFrom, 
                $dateTo
            );

            return response()->json([
                'success' => true,
                'data' => $statusHistory,
                'message' => 'Status history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving status history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status history for a specific quote
     */
    public function getQuoteHistory($quoteId): JsonResponse
    {
        try {
            $statusHistory = $this->statusHistoryService->getQuoteStatusHistory($quoteId);

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
     * Get status history for a specific invoice
     */
    public function getInvoiceHistory($invoiceId): JsonResponse
    {
        try {
            $statusHistory = $this->statusHistoryService->getInvoiceStatusHistory($invoiceId);

            return response()->json([
                'success' => true,
                'data' => $statusHistory,
                'message' => 'Invoice status history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoice status history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status history statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_quotes_status_changes' => $this->statusHistoryService->getStatusHistory('Quotes')->count(),
                'total_invoice_status_changes' => $this->statusHistoryService->getStatusHistory('Invoice')->count(),
                'recent_changes' => $this->statusHistoryService->getStatusHistory()
                ->take(10)
                ->map(function($item) {
                    return [
                        'id' => $item->id,
                        'type' => $item->type,
                        'status' => $item->status,
                        'record_id' => $item->record_id,
                        'date' => $item->formatted_date,
                        'created_by' => $item->user->name ?? 'Unknown User',
                        'user_id' => $item->user->id ?? null,
                        'notes' => $item->notes
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Status history statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving status history statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
