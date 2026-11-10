<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\Sales\SalesTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuoteController extends Controller
{
    use SalesSupport;

    /** Older quotes used Pending / Closed / Rejected. They are shown with the current names. */
    private const LEGACY = ['Pending' => 'sent', 'Closed' => 'declined', 'Rejected' => 'declined'];
    private const DB_STATUS = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'invoiced' => 'Invoiced'];

    // ------------------------------------------------------------------ list

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $today = now()->toDateString();
        $base = Quote::where('company_id', $b->company_id);

        $q = (clone $base)->with('contact');
        $this->applyStatus($q, $request->query('status', 'all'), $today);

        if ($s = trim((string) $request->query('search', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('quote_number', 'like', "%{$s}%")
                    ->orWhere('reference', 'like', "%{$s}%")
                    ->orWhereHas('contact', function ($c) use ($s) {
                        $c->where('contact_first_name', 'like', "%{$s}%")
                            ->orWhere('contact_last_name', 'like', "%{$s}%")
                            ->orWhere('contact_company_name', 'like', "%{$s}%");
                    });
            });
        }

        $page = $q->orderByDesc('quote_date')->orderByDesc('quote_id')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 50));

        $rows = $page->getCollection()->map(fn (Quote $x) => [
            'id' => $x->quote_id,
            'number' => $x->quote_number,
            'customer_name' => $this->contactName($x->contact),
            'reference' => $x->reference,
            'issue_date' => optional($x->quote_date)->toDateString(),
            'expiry_date' => optional($x->expiration_date)->toDateString(),
            'total' => $this->money($x->total),
            'status' => $this->statusOf($x, $today),
        ])->values();

        $counts = [];
        foreach (['all', 'draft', 'sent', 'accepted', 'declined', 'invoiced'] as $s) {
            $cq = (clone $base);
            $this->applyStatus($cq, $s, $today);
            $counts[$s] = $cq->count();
        }

        return response()->json([
            'data' => $rows,
            'counts' => $counts,
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    private function applyStatus($query, string $status, string $today): void
    {
        switch ($status) {
            case 'draft':
                $query->where('status', 'Draft');
                break;
            case 'sent':
                $query->whereIn('status', ['Sent', 'Pending']);
                break;
            case 'accepted':
                $query->where('status', 'Accepted');
                break;
            case 'declined':
                $query->whereIn('status', ['Declined', 'Rejected', 'Closed']);
                break;
            case 'invoiced':
                $query->where('status', 'Invoiced');
                break;
        }
    }

    /** draft | sent | expired | accepted | declined | invoiced */
    private function statusOf(Quote $q, string $today): string
    {
        $s = self::LEGACY[$q->status] ?? strtolower((string) $q->status);
        if ($s === 'sent' && $q->expiration_date && $q->expiration_date->toDateString() < $today) {
            return 'expired';
        }
        return $s;
    }

    // ------------------------------------------------------------------ one

    public function show($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $quote = Quote::where('company_id', $b->company_id)->with(['contact', 'items'])->find($id);
        if (!$quote) {
            return $this->failure('Quote not found.', 404);
        }
        return response()->json(['data' => $this->document($quote, $b)]);
    }

    private function document(Quote $q, $b): array
    {
        $status = $this->statusOf($q, now()->toDateString());
        $mode = $q->amounts_are ?: 'exclusive';
        $percent = (float) $q->discount_percent;
        $lines = $q->items->map(fn (QuoteItem $it) => [
            'description' => $it->description ?: $it->product_service,
            'quantity' => (float) $it->quantity,
            'rate' => $this->money($it->rate),
            'gst_rate' => (float) ($it->gst_rate ?? 10),
            'amount' => $this->money($it->amount),
            'gst' => $this->money($it->gst),
        ])->values();

        return [
            'id' => $q->quote_id,
            'kind' => 'quote',
            'title' => 'Quote',
            'number' => $q->quote_number,
            'status' => $status,
            'reference' => $q->reference,
            'issue_date' => optional($q->quote_date)->toDateString(),
            'expiry_date' => optional($q->expiration_date)->toDateString(),
            'amounts_are' => $mode,
            'seller' => $this->presentSeller($b),
            'customer' => $this->presentCustomer($q->contact),
            'lines' => $lines,
            'discount_method' => $percent > 0 ? 'percentage' : ($q->discount_amount > 0 ? 'fixed' : 'percentage'),
            'discount_value' => $percent > 0 ? $this->money($percent) : $this->money($q->discount_amount),
            'notes' => $q->notes,
            'terms' => $q->terms,
            'invoice_id' => $q->invoice_id,
            'totals' => [
                'discount' => $this->money($q->discount_amount),
                'subtotal' => $this->money($q->subtotal),
                'gst' => $this->money($q->tax_amount),
                'total' => $this->money($q->total),
            ],
            'can' => [
                'edit' => in_array($status, ['draft', 'sent', 'expired'], true),
                'delete' => $status === 'draft',
                'send' => $status === 'draft',
                'accept' => in_array($status, ['sent', 'expired'], true),
                'decline' => in_array($status, ['sent', 'expired'], true),
                'convert' => in_array($status, ['draft', 'sent', 'expired', 'accepted'], true),
            ],
        ];
    }

    // ------------------------------------------------------------------ save

    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $data = $this->validated($request, $b);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $send = $request->input('action') === 'send';

        $quote = DB::transaction(function () use ($b, $data, $send) {
            $q = new Quote();
            $q->company_id = $b->company_id;
            $q->quote_number = $this->nextNumber('quotes', 'quote_number', (int) $b->company_id, 'QU');
            $q->status = $send ? 'Sent' : 'Draft';
            $this->fill($q, $data);
            $q->save();
            $this->saveLines($q, $data);
            return $q;
        });

        $quote->load(['contact', 'items']);
        return response()->json(['data' => $this->document($quote, $b), 'message' => $send ? 'Quote marked as sent.' : 'Draft saved.'], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $quote = Quote::where('company_id', $b->company_id)->find($id);
        if (!$quote) {
            return $this->failure('Quote not found.', 404);
        }
        $status = $this->statusOf($quote, now()->toDateString());
        if (!in_array($status, ['draft', 'sent', 'expired'], true)) {
            return $this->failure('A ' . $status . ' quote can no longer be changed.');
        }
        $data = $this->validated($request, $b);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $send = $quote->status === 'Draft' && $request->input('action') === 'send';

        DB::transaction(function () use ($quote, $data, $send) {
            $this->fill($quote, $data);
            if ($send) {
                $quote->status = 'Sent';
            }
            $quote->save();
            QuoteItem::where('quote_id', $quote->quote_id)->delete();
            $this->saveLines($quote, $data);
        });

        $quote->load(['contact', 'items']);
        return response()->json(['data' => $this->document($quote, $b), 'message' => $send ? 'Quote marked as sent.' : 'Quote saved.']);
    }

    private function validated(Request $request, $b)
    {
        $rules = $this->lineRules() + [
            'customer_id' => ['required', 'integer'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
        $v = Validator::make($request->all(), $rules, $this->lineMessages() + [
            'customer_id.required' => 'Choose a customer.',
            'issue_date.required' => 'Enter the quote date.',
            'expiry_date.required' => 'Enter how long the quote is valid for.',
            'expiry_date.after_or_equal' => 'The quote cannot expire before its date.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $customer = Contact::where('company_id', $b->company_id)->where('contact_id', $request->customer_id)->first();
        if (!$customer) {
            return $this->failure('Please fix the highlighted fields.', 422, ['customer_id' => ['Choose a customer from your list.']]);
        }
        $mode = $this->amountsAre($b, $request->input('amounts_are'));
        $method = $request->input('discount_method', 'percentage');
        $dv = (float) $request->input('discount_value', 0);
        if ($method === 'percentage' && $dv > 100) {
            return $this->failure('Please fix the highlighted fields.', 422, ['discount_value' => ['A percentage discount cannot be more than 100.']]);
        }
        if ($this->tooLarge($request->input('lines'))) {
            return $this->failure('Please fix the highlighted fields.', 422, ['lines' => ['The total is too large. The most you can enter is $9,999,999.99 before GST.']]);
        }
        $lines = array_map(fn ($l) => [
            'description' => trim($l['description']),
            'quantity' => (float) $l['quantity'],
            'rate' => (float) $l['rate'],
            'gst_rate' => $mode === 'no_gst' ? 0 : (int) ($l['gst_rate'] ?? SalesTotals::GST_RATE),
        ], $request->input('lines'));

        return [
            'customer' => $customer,
            'request' => $request,
            'mode' => $mode,
            'method' => $method,
            'discount_value' => $dv,
            'lines' => $lines,
            'totals' => SalesTotals::compute($lines, $mode, $method, $dv),
        ];
    }

    private function fill(Quote $q, array $d): void
    {
        $r = $d['request'];
        $t = $d['totals'];
        $q->contact_id = $d['customer']->contact_id;
        $q->quote_date = $r->issue_date;
        $q->expiration_date = $r->expiry_date;
        $q->reference = $r->reference ?: null;
        $q->notes = $r->notes ?: null;
        $q->terms = $r->terms ?: null;
        $q->amounts_are = $d['mode'];
        $q->customer_email = $d['customer']->contact_email_address;
        $q->billing_address = implode(', ', $this->contactAddress($d['customer']));
        $q->discount_percent = $d['method'] === 'percentage' ? min($d['discount_value'], 100) : 0;
        $q->discount_amount = $t['discount'];
        $q->subtotal = $t['subtotal'];
        $q->tax_amount = $t['gst'];
        $q->total = $t['total'];
    }

    private function saveLines(Quote $q, array $d): void
    {
        foreach ($d['lines'] as $i => $line) {
            $calc = $d['totals']['lines'][$i];
            QuoteItem::create([
                'quote_id' => $q->quote_id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'rate' => $line['rate'],
                'gst_rate' => $line['gst_rate'],
                'amount' => $calc['ex_gst'],
                'gst' => $calc['gst'],
            ]);
        }
    }

    // ------------------------------------------------------------------ actions

    public function setStatus(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $quote = Quote::where('company_id', $b->company_id)->with(['contact', 'items'])->find($id);
        if (!$quote) {
            return $this->failure('Quote not found.', 404);
        }
        $to = $request->input('status');
        if (!in_array($to, ['sent', 'accepted', 'declined'], true)) {
            return $this->failure('Choose sent, accepted or declined.');
        }
        $from = $this->statusOf($quote, now()->toDateString());
        $allowed = [
            'sent' => ['draft'],
            'accepted' => ['sent', 'expired'],
            'declined' => ['sent', 'expired'],
        ];
        if (!in_array($from, $allowed[$to], true)) {
            return $this->failure("A {$from} quote cannot be marked as {$to}.");
        }
        $quote->status = self::DB_STATUS[$to];
        $quote->save();
        return response()->json(['data' => $this->document($quote, $b), 'message' => 'Quote marked as ' . $to . '.']);
    }

    public function destroy($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $quote = Quote::where('company_id', $b->company_id)->find($id);
        if (!$quote) {
            return $this->failure('Quote not found.', 404);
        }
        if ($quote->status !== 'Draft') {
            return $this->failure('Only drafts can be deleted. Mark the quote as declined instead.');
        }
        $quote->delete();
        return response()->json(['message' => 'Draft deleted.']);
    }

    /** Turns a quote into a draft invoice with the same customer and lines. */
    public function convert($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $quote = Quote::where('company_id', $b->company_id)->with('items')->find($id);
        if (!$quote) {
            return $this->failure('Quote not found.', 404);
        }
        $status = $this->statusOf($quote, now()->toDateString());
        if (!in_array($status, ['draft', 'sent', 'expired', 'accepted'], true)) {
            return $this->failure($status === 'invoiced' ? 'This quote has already been turned into an invoice.' : 'A declined quote cannot be invoiced.');
        }

        $invoice = DB::transaction(function () use ($b, $quote) {
            $mode = $this->amountsAre($b, $quote->amounts_are);
            $method = $quote->discount_percent > 0 ? 'percentage' : 'fixed';
            $value = $quote->discount_percent > 0 ? (float) $quote->discount_percent : (float) $quote->discount_amount;
            $lines = $quote->items->map(fn ($it) => [
                'quantity' => (float) $it->quantity,
                'rate' => (float) $it->rate,
                'gst_rate' => $mode === 'no_gst' ? 0 : (int) ($it->gst_rate ?? 10),
            ])->all();
            // Quote lines store the ex-GST amount; rebuild from the original typed rate so totals match the quote.
            $t = SalesTotals::compute($lines, $mode, $method, $value);

            $inv = new Invoice();
            $inv->company_id = $b->company_id;
            $inv->invoice_number = $this->nextNumber('invoices', 'invoice_number', (int) $b->company_id, 'INV');
            $inv->invoice_status = 'Draft';
            $inv->invoice_payment_status = 'Unpaid';
            $inv->invoice_paid_amount = 0;
            $inv->sent_to = $quote->contact_id;
            $inv->invoice_date = now()->toDateString();
            $inv->invoice_due_date = now()->addDays(14)->toDateString();
            $inv->terms = 'Due in 14 days';
            $inv->reference = $quote->reference ?: $quote->quote_number;
            $inv->notes = $quote->notes;
            $inv->amounts_are = $mode;
            $inv->discount_method = $method;
            $inv->discount_type = $value;
            $inv->discount_amount = $t['discount'];
            $inv->tax_rate = 0;
            $inv->tax_amount = 0;
            $inv->invoice_amount = $t['gross'];
            $inv->subtotal = $t['subtotal'];
            $inv->gst_amount = $t['gst'];
            $inv->invoice_total = $t['total'];
            $inv->save();

            foreach ($quote->items as $i => $it) {
                $c = $t['lines'][$i];
                InvoiceItem::create([
                    'invoice_id' => $inv->invoice_id,
                    'item_description' => $it->description ?: $it->product_service,
                    'item_quantity' => $it->quantity,
                    'item_rate' => $it->rate,
                    'item_gst_rate' => $mode === 'no_gst' ? 0 : ($it->gst_rate ?? 10),
                    'item_cost' => $c['ex_gst'],
                    'item_gst' => $c['gst'],
                    'item_total' => $c['inc_gst'],
                ]);
            }

            $quote->status = 'Invoiced';
            $quote->invoice_id = $inv->invoice_id;
            $quote->save();
            return $inv;
        });

        return response()->json(['data' => ['invoice_id' => $invoice->invoice_id, 'invoice_number' => $invoice->invoice_number], 'message' => 'Draft invoice created from the quote.'], 201);
    }
}
