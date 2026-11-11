<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use Carbon\Carbon;

/**
 * Works out how each customer pays, from their issued invoices and the payments recorded against them.
 * The rules are plain on purpose, so they are easy to read and change:
 *   overdue     - has at least one unpaid invoice past its due date
 *   pays_late   - at least MIN_HISTORY paid invoices and half or more were paid after the due date
 *   reliable    - at least MIN_HISTORY paid invoices, none paid late, nothing overdue now
 * Drafts, cancelled and rejected invoices are ignored.
 */
class CustomerInsights
{
    public const MIN_HISTORY = 3;
    private const NOT_ISSUED = ['Draft', 'Cancelled', 'Rejected'];

    /** @param int[] $contactIds  @return array<int,array> keyed by contact id */
    public function forContacts(int $companyId, array $contactIds, ?string $today = null): array
    {
        $today = $today ?: Carbon::today()->toDateString();
        $out = [];
        foreach ($contactIds as $id) {
            $out[$id] = $this->blank();
        }
        if (!$contactIds) {
            return $out;
        }

        $invoices = Invoice::where('company_id', $companyId)
            ->whereIn('sent_to', $contactIds)
            ->whereNotIn('invoice_status', self::NOT_ISSUED)
            ->get(['invoice_id', 'sent_to', 'invoice_total', 'invoice_paid_amount', 'invoice_date', 'invoice_due_date']);

        // Last payment date for each invoice.
        $lastPaid = InvoiceTransaction::whereIn('invoice_id', $invoices->pluck('invoice_id'))
            ->selectRaw('invoice_id, MAX(payment_date) as last_date')
            ->groupBy('invoice_id')->pluck('last_date', 'invoice_id');

        foreach ($invoices as $i) {
            $p = &$out[$i->sent_to];
            $p['invoice_count']++;
            $due = $i->invoice_due_date ? $i->invoice_due_date->toDateString() : null;
            $owing = max(0, (float) $i->invoice_total - (float) $i->invoice_paid_amount);

            if ($owing > 0.004) {
                $p['outstanding'] += $owing;
                if ($due && $due < $today) {
                    $p['overdue'] += $owing;
                    $p['overdue_count']++;
                    $days = Carbon::parse($due)->diffInDays(Carbon::parse($today));
                    $p['oldest_overdue_days'] = max($p['oldest_overdue_days'], $days);
                }
            } elseif ($due && isset($lastPaid[$i->invoice_id])) {
                $p['paid_count']++;
                $late = Carbon::parse($due)->diffInDays(Carbon::parse($lastPaid[$i->invoice_id]), false);
                if ($late > 0) {
                    $p['late_count']++;
                    $p['late_days_total'] += $late;
                }
            }
            unset($p);
        }

        foreach ($out as &$p) {
            $p['outstanding'] = round($p['outstanding'], 2);
            $p['overdue'] = round($p['overdue'], 2);
            $p['avg_days_late'] = $p['late_count'] ? (int) round($p['late_days_total'] / $p['late_count']) : 0;
            unset($p['late_days_total']);
            $p['flags'] = $this->flags($p);
        }
        return $out;
    }

    private function blank(): array
    {
        return ['invoice_count' => 0, 'paid_count' => 0, 'late_count' => 0, 'late_days_total' => 0,
            'outstanding' => 0.0, 'overdue' => 0.0, 'overdue_count' => 0, 'oldest_overdue_days' => 0];
    }

    private function flags(array $p): array
    {
        $flags = [];
        if ($p['overdue_count'] > 0) {
            $flags[] = 'overdue';
        }
        if ($p['paid_count'] >= self::MIN_HISTORY) {
            if ($p['late_count'] * 2 >= $p['paid_count']) {
                $flags[] = 'pays_late';
            } elseif ($p['late_count'] === 0 && $p['overdue_count'] === 0) {
                $flags[] = 'reliable';
            }
        }
        return $flags;
    }
}
