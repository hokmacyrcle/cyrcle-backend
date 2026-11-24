<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Company;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * For the accounting firm: every overdue invoice across all of its client businesses, grouped by client.
 * Only the firm's own active client businesses are included.
 */
class FirmOverdueController extends Controller
{
    use SalesSupport;

    private const NOT_ISSUED = ['Draft', 'Cancelled', 'Rejected'];
    private const INVOICES_PER_CLIENT = 20;

    public function show(): JsonResponse
    {
        $user = auth()->guard('api')->user() ?? request()->user();
        $firm = Company::where('user_id', $user->id)->where('company_type', 'Subscriber')->where('status', 'active')->first();
        if (!$firm) {
            return $this->failure('Only an accounting firm can see this.', 403);
        }
        $clients = Company::where('company_type', 'Client')->where('subscriber_id', $firm->company_id)
            ->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 'active');
            })->get(['company_id', 'company_name'])->keyBy('company_id');

        // Dates are Australian calendar dates, while the server clock is UTC.
        $today = Carbon::now('Australia/Sydney')->toDateString();

        $overdue = Invoice::whereIn('company_id', $clients->keys())
            ->whereNotIn('invoice_status', self::NOT_ISSUED)
            ->whereRaw('invoice_paid_amount < invoice_total')
            ->whereNotNull('invoice_due_date')->whereDate('invoice_due_date', '<', $today)
            ->with('contact')
            ->orderBy('invoice_due_date')
            ->get();

        $byClient = [];
        foreach ($overdue as $i) {
            $c = &$byClient[$i->company_id];
            $owing = max(0, (float) $i->invoice_total - (float) $i->invoice_paid_amount);
            $days = (int) Carbon::parse($i->invoice_due_date->toDateString())->diffInDays(Carbon::parse($today));
            $c['overdue'] = ($c['overdue'] ?? 0) + $owing;
            $c['count'] = ($c['count'] ?? 0) + 1;
            $c['oldest_days'] = max($c['oldest_days'] ?? 0, $days);
            if (count($c['invoices'] ?? []) < self::INVOICES_PER_CLIENT) {    // oldest first
                $c['invoices'][] = [
                    'id' => $i->invoice_id,
                    'number' => $i->invoice_number ?: ('#' . $i->invoice_id),
                    'customer_name' => $this->contactName($i->contact),
                    'due_date' => $i->invoice_due_date->toDateString(),
                    'days_overdue' => $days,
                    'amount_due' => $this->money($owing),
                ];
            }
            unset($c);
        }

        $rows = [];
        foreach ($byClient as $companyId => $c) {
            $rows[] = [
                'company_id' => $companyId,
                'company_name' => $clients[$companyId]->company_name,
                'overdue' => $this->money($c['overdue']),
                'count' => $c['count'],
                'oldest_days' => $c['oldest_days'],
                'invoices' => $c['invoices'],
            ];
        }
        usort($rows, fn ($a, $b) => $b['overdue'] <=> $a['overdue']);     // biggest problem first

        return response()->json(['data' => [
            'total' => $this->money(array_sum(array_column($rows, 'overdue'))),
            'count' => array_sum(array_column($rows, 'count')),
            'clients' => $rows,
            'client_count' => $clients->count(),
        ]]);
    }
}
