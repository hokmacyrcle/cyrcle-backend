<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;

/** Numbers for the Sales overview. Everything is limited to the business the user is working in. */
class SummaryController extends Controller
{
    use SalesSupport;

    private const NOT_ISSUED = ['Draft', 'Cancelled', 'Rejected'];

    /** The business being worked in: used by the invoice and quote forms to know whether GST applies. */
    public function businessInfo(): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        return response()->json(['data' => [
            'name' => $b->company_name,
            'abn' => $b->company_abn,
            'gst_registered' => (bool) $b->gst_registered,
            'has_bank_details' => (bool) ($b->bank_bsb && $b->bank_account_number),
        ]]);
    }

    public function show(): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $cid = (int) $b->company_id;
        $today = now()->toDateString();

        $issued = fn () => Invoice::where('company_id', $cid)->whereNotIn('invoice_status', self::NOT_ISSUED);
        $owing = fn () => $issued()->whereRaw('invoice_paid_amount < invoice_total');
        $owed = (float) $owing()->selectRaw('COALESCE(SUM(invoice_total - invoice_paid_amount), 0) as s')->value('s');
        $overdueQuery = fn () => $owing()->whereNotNull('invoice_due_date')->whereDate('invoice_due_date', '<', $today);

        $overdueRows = $overdueQuery()->with('contact')->orderBy('invoice_due_date')->limit(5)->get()->map(fn (Invoice $i) => [
            'id' => $i->invoice_id,
            'number' => $i->invoice_number ?: ('#' . $i->invoice_id),
            'customer_name' => $this->contactName($i->contact),
            'due_date' => $i->invoice_due_date->toDateString(),
            'days_overdue' => (int) $i->invoice_due_date->diffInDays(now()->startOfDay()),
            'amount_due' => $this->money($i->invoice_total - $i->invoice_paid_amount),
        ]);

        $received30 = InvoiceTransaction::whereIn('invoice_id', Invoice::where('company_id', $cid)->select('invoice_id'))
            ->whereDate('payment_date', '>=', now()->subDays(30)->toDateString());

        $recent = Invoice::where('company_id', $cid)->with('contact')
            ->orderByDesc('invoice_date')->orderByDesc('invoice_id')->limit(5)->get()->map(function (Invoice $i) use ($today) {
                $status = $i->invoice_status === 'Cancelled' ? 'void'
                    : ($i->invoice_status === 'Draft' ? 'draft'
                    : (((float) $i->invoice_paid_amount >= (float) $i->invoice_total) ? 'paid'
                    : (($i->invoice_due_date && $i->invoice_due_date->toDateString() < $today) ? 'overdue' : 'awaiting')));
                return [
                    'id' => $i->invoice_id,
                    'number' => $i->invoice_number ?: ('#' . $i->invoice_id),
                    'customer_name' => $this->contactName($i->contact),
                    'issue_date' => $i->invoice_date->toDateString(),
                    'total' => $this->money($i->invoice_total),
                    'status' => $status,
                ];
            });

        // Last six months: invoiced (issued invoices by invoice date) and received (payments by payment date).
        $from = now()->startOfMonth()->subMonths(5);
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $from->copy()->addMonths($i);
            $months[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'label' => $m->format('M'), 'invoiced' => 0.0, 'received' => 0.0];
        }
        foreach ($issued()->whereDate('invoice_date', '>=', $from->toDateString())->get(['invoice_date', 'invoice_total']) as $i) {
            $k = $i->invoice_date->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['invoiced'] += (float) $i->invoice_total;
            }
        }
        $payments = InvoiceTransaction::whereIn('invoice_id', Invoice::where('company_id', $cid)->select('invoice_id'))
            ->whereDate('payment_date', '>=', $from->toDateString())->get(['payment_date', 'payment_amount']);
        foreach ($payments as $p) {
            $k = $p->payment_date->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['received'] += (float) $p->payment_amount;
            }
        }

        $drafts = Invoice::where('company_id', $cid)->where('invoice_status', 'Draft');
        $quotes = Quote::where('company_id', $cid);

        return response()->json(['data' => [
            'business' => [
                'name' => $b->company_name,
                'abn' => $b->company_abn,
                'gst_registered' => (bool) $b->gst_registered,
                'has_bank_details' => (bool) ($b->bank_bsb && $b->bank_account_number),
            ],
            'owed' => ['amount' => $this->money($owed), 'count' => $owing()->count()],
            'overdue' => [
                'amount' => $this->money($overdueQuery()->selectRaw('COALESCE(SUM(invoice_total - invoice_paid_amount), 0) as s')->value('s')),
                'count' => $overdueQuery()->count(),
            ],
            'received_30_days' => ['amount' => $this->money($received30->sum('payment_amount')), 'count' => $received30->count()],
            'drafts' => ['amount' => $this->money($drafts->sum('invoice_total')), 'count' => $drafts->count()],
            'quotes_awaiting' => [
                'count' => (clone $quotes)->whereIn('status', ['Sent', 'Pending'])->count(),
                'amount' => $this->money((clone $quotes)->whereIn('status', ['Sent', 'Pending'])->sum('total')),
            ],
            'overdue_list' => $overdueRows,
            'recent' => $recent,
            'monthly' => array_values(array_map(fn ($m) => [
                'month' => $m['month'], 'label' => $m['label'],
                'invoiced' => round($m['invoiced'], 2), 'received' => round($m['received'], 2),
            ], $months)),
            'totals' => [
                'invoices' => Invoice::where('company_id', $cid)->count(),
                'quotes' => (clone $quotes)->count(),
                'customers' => Contact::where('company_id', $cid)->where(function ($w) {
                    $w->whereNull('contact_category')->orWhereNotIn('contact_category', ['Supplier', 'Vendor']);
                })->where('contact_status', 'Active')->count(),
            ],
        ]]);
    }
}
