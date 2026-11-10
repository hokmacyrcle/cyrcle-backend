<?php

namespace App\Services;

use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;

class StatusHistoryService
{
    /**
     * Record a status change in the history
     *
     * @param string $type 'Quotes' or 'Invoice'
     * @param int $recordId The ID of the quote or invoice
     * @param string $status The new status
     * @param string|null $notes Optional notes about the status change
     * @return StatusHistory
     */
    public function recordStatusChange(string $type, int $recordId, string $status, ?string $notes = null): StatusHistory
    {
        // Try multiple approaches to get the authenticated user
        $user = null;
        $userId = null;
        
        // First try API guard
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            $userId = $user ? $user->id : null;
        }
        
        // Fallback to default guard
        if (!$userId && Auth::check()) {
            $user = Auth::user();
            $userId = $user ? $user->id : null;
        }
        
        // Last resort: try to get from request context
        if (!$userId && request()->user()) {
            $user = request()->user();
            $userId = $user ? $user->id : null;
        }
        
        if (!$userId) {
            throw new \Exception('User must be authenticated to record status changes. ' . 
                'API guard check: ' . (Auth::guard('api')->check() ? 'true' : 'false') . 
                ', Default guard check: ' . (Auth::check() ? 'true' : 'false') . 
                ', Request user: ' . (request()->user() ? 'exists' : 'null'));
        }

        return StatusHistory::create([
            'date' => now(),
            'created_by' => $userId,
            'type' => $type,
            'status' => $status,
            'record_id' => $recordId,
            'notes' => $notes,
        ]);
    }

    /**
     * Record quote status change
     *
     * @param int $quoteId
     * @param string $status
     * @param string|null $notes
     * @return StatusHistory
     */
    public function recordQuoteStatusChange(int $quoteId, string $status, ?string $notes = null): StatusHistory
    {
        return $this->recordStatusChange('Quotes', $quoteId, $status, $notes);
    }

    /**
     * Record invoice status change
     *
     * @param int $invoiceId
     * @param string $status
     * @param string|null $notes
     * @return StatusHistory
     */
    public function recordInvoiceStatusChange(int $invoiceId, string $status, ?string $notes = null): StatusHistory
    {
        return $this->recordStatusChange('Invoice', $invoiceId, $status, $notes);
    }

    /**
     * Get status history for a specific quote
     *
     * @param int $quoteId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getQuoteStatusHistory(int $quoteId)
    {
        return StatusHistory::byRecord('Quotes', $quoteId)
            ->with('user:id,name')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get status history for a specific invoice
     *
     * @param int $invoiceId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInvoiceStatusHistory(int $invoiceId)
    {
        return StatusHistory::byRecord('Invoice', $invoiceId)
            ->with('user:id,name')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all status history with optional filtering
     *
     * @param string|null $type
     * @param string|null $status
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStatusHistory(?string $type = null, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $query = StatusHistory::with('user:id,name');

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        return $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
