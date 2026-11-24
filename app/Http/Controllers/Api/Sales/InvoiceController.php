<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTransaction;
use App\Services\Sales\SalesTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    use SalesSupport;

    private const NOT_ISSUED = ['Draft', 'Cancelled', 'Rejected'];
    private const METHODS = ['Bank transfer', 'Cash', 'Cheque', 'Card', 'Other'];

    // ------------------------------------------------------------------ list

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $today = now()->toDateString();

        $base = Invoice::where('company_id', $b->company_id);

        $q = (clone $base)->with('contact');
        $status = $request->query('status', 'all');
        $this->applyStatus($q, $status, $today);

        if ($s = trim((string) $request->query('search', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('invoice_number', 'like', "%{$s}%")
                    ->orWhere('reference', 'like', "%{$s}%")
                    ->orWhereHas('contact', function ($c) use ($s) {
                        $c->where('contact_first_name', 'like', "%{$s}%")
                            ->orWhere('contact_last_name', 'like', "%{$s}%")
                            ->orWhere('contact_company_name', 'like', "%{$s}%");
                    });
            });
        }

        $page = $q->orderByDesc('invoice_date')->orderByDesc('invoice_id')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 50));

        $rows = $page->getCollection()->map(fn (Invoice $i) => $this->row($i, $today))->values();

        $counts = [];
        foreach (['all', 'draft', 'awaiting', 'overdue', 'paid', 'void'] as $s) {
            $cq = (clone $base);
            $this->applyStatus($cq, $s, $today);
            $counts[$s] = $cq->count();
        }

        return response()->json([
            'data' => $rows,
            'counts' => $counts,
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    private function applyStatus($query, string $status, string $today): void
    {
        $unpaid = 'invoice_paid_amount < invoice_total';
        switch ($status) {
            case 'draft':
                $query->where('invoice_status', 'Draft');
                break;
            case 'void':
                $query->where('invoice_status', 'Cancelled');
                break;
            case 'paid':
                $query->whereNotIn('invoice_status', self::NOT_ISSUED)->whereRaw('invoice_paid_amount >= invoice_total');
                break;
            case 'overdue':
                $query->whereNotIn('invoice_status', self::NOT_ISSUED)->whereRaw($unpaid)
                    ->whereNotNull('invoice_due_date')->whereDate('invoice_due_date', '<', $today);
                break;
            case 'awaiting':
                $query->whereNotIn('invoice_status', self::NOT_ISSUED)->whereRaw($unpaid)
                    ->where(function ($w) use ($today) {
                        $w->whereNull('invoice_due_date')->orWhereDate('invoice_due_date', '>=', $today);
                    });
                break;
        }
    }

    /** draft | awaiting | overdue | paid | void */
    private function statusOf(Invoice $i, string $today): string
    {
        if ($i->invoice_status === 'Cancelled') {
            return 'void';
        }
        if ($i->invoice_status === 'Draft') {
            return 'draft';
        }
        if ((float) $i->invoice_paid_amount >= (float) $i->invoice_total) {
            return 'paid';
        }
        if ($i->invoice_due_date && $i->invoice_due_date->toDateString() < $today) {
            return 'overdue';
        }
        return 'awaiting';
    }

    private function row(Invoice $i, string $today): array
    {
        $status = $this->statusOf($i, $today);
        return [
            'id' => $i->invoice_id,
            'number' => $i->invoice_number ?: ('#' . $i->invoice_id),
            'customer_name' => $this->contactName($i->contact),
            'reference' => $i->reference,
            'issue_date' => optional($i->invoice_date)->toDateString(),
            'due_date' => optional($i->invoice_due_date)->toDateString(),
            'total' => $this->money($i->invoice_total),
            'amount_due' => $status === 'void' ? 0.0 : $this->money(max(0, $i->invoice_total - $i->invoice_paid_amount)),
            'status' => $status,
            'recurring' => (bool) $i->recurring_invoice_id,
        ];
    }

    // ------------------------------------------------------------------ one

    public function show($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $invoice = Invoice::where('company_id', $b->company_id)->with(['contact', 'items', 'transactions'])->find($id);
        if (!$invoice) {
            return $this->failure('Invoice not found.', 404);
        }
        return response()->json(['data' => $this->document($invoice, $b)]);
    }

    private function document(Invoice $i, $b): array
    {
        $today = now()->toDateString();
        $status = $this->statusOf($i, $today);
        $hasPayments = $i->transactions->count() > 0;
        $lastReminder = \App\Models\InvoiceReminder::where('invoice_id', $i->invoice_id)->max('created_at');
        $mode = $i->amounts_are ?: 'exclusive';
        $isTax = $b->gst_registered && $mode !== 'no_gst';

        $lines = $i->items->map(fn (InvoiceItem $it) => [
            'description' => $it->item_description,
            'quantity' => (float) $it->item_quantity,
            'rate' => $this->money($it->item_rate),
            'gst_rate' => (float) ($it->item_gst_rate ?? 10),
            'amount' => $this->money($it->item_cost),
            'gst' => $this->money($it->item_gst),
        ])->values();

        $discountValue = $i->discount_method === 'fixed' ? (float) $i->discount_amount : (float) $i->discount_type;

        return [
            'id' => $i->invoice_id,
            'kind' => 'invoice',
            'title' => $isTax ? 'Tax invoice' : 'Invoice',
            'number' => $i->invoice_number ?: ('#' . $i->invoice_id),
            'status' => $status,
            'recurring' => (bool) $i->recurring_invoice_id,
            'last_reminder' => $lastReminder ? substr((string) $lastReminder, 0, 10) : null,
            'reference' => $i->reference,
            'issue_date' => optional($i->invoice_date)->toDateString(),
            'due_date' => optional($i->invoice_due_date)->toDateString(),
            'terms' => $i->terms,
            'amounts_are' => $mode,
            'seller' => $this->presentSeller($b),
            'customer' => $this->presentCustomer($i->contact),
            'lines' => $lines,
            'discount_method' => $i->discount_method ?: 'percentage',
            'discount_value' => $this->money($discountValue),
            'notes' => $i->notes,
            'totals' => [
                'gross' => $this->money($i->invoice_amount),
                'discount' => $this->money($i->discount_amount),
                'subtotal' => $this->money($i->subtotal),
                'gst' => $this->money($i->gst_amount),
                'total' => $this->money($i->invoice_total),
                'paid' => $this->money($i->invoice_paid_amount),
                'amount_due' => $status === 'void' ? 0.0 : $this->money(max(0, $i->invoice_total - $i->invoice_paid_amount)),
            ],
            'payments' => $i->transactions->sortByDesc('payment_date')->values()->map(fn (InvoiceTransaction $t) => [
                'id' => $t->transaction_id,
                'date' => optional($t->payment_date)->toDateString(),
                'amount' => $this->money($t->payment_amount),
                'method' => $t->payment_method,
                'reference' => $t->reference_number,
                'notes' => $t->notes,
            ]),
            'can' => [
                'edit' => in_array($status, ['draft', 'awaiting', 'overdue'], true) && !$hasPayments,
                'delete' => $status === 'draft',
                'issue' => $status === 'draft',
                'void' => in_array($status, ['awaiting', 'overdue'], true) && !$hasPayments,
                'pay' => in_array($status, ['awaiting', 'overdue'], true),
                'remind' => $status === 'overdue',
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
        $issue = $request->input('action') === 'issue';
        if ($issue && ($err = $this->cannotIssue($b))) {
            return $this->failure($err);
        }

        $invoice = DB::transaction(function () use ($b, $data, $issue) {
            $number = $this->nextNumber('invoices', 'invoice_number', (int) $b->company_id, 'INV');
            $invoice = new Invoice();
            $invoice->company_id = $b->company_id;
            $invoice->invoice_number = $number;
            $invoice->invoice_status = $issue ? 'Sent' : 'Draft';
            $invoice->invoice_payment_status = 'Unpaid';
            $invoice->invoice_paid_amount = 0;
            $this->fill($invoice, $data);
            $invoice->save();
            $this->saveLines($invoice, $data);
            return $invoice;
        });

        $invoice->load(['contact', 'items', 'transactions']);
        return response()->json(['data' => $this->document($invoice, $b), 'message' => $issue ? 'Invoice issued.' : 'Draft saved.'], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $invoice = Invoice::where('company_id', $b->company_id)->with('transactions')->find($id);
        if (!$invoice) {
            return $this->failure('Invoice not found.', 404);
        }
        $status = $this->statusOf($invoice, now()->toDateString());
        if ($status === 'void' || $status === 'paid') {
            return $this->failure('A ' . $status . ' invoice can no longer be changed.');
        }
        if ($invoice->transactions->count() > 0) {
            return $this->failure('This invoice has payments recorded, so it cannot be changed. Remove the payment first.');
        }
        $data = $this->validated($request, $b);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $issue = $invoice->invoice_status === 'Draft' && $request->input('action') === 'issue';
        if ($issue && ($err = $this->cannotIssue($b))) {
            return $this->failure($err);
        }

        DB::transaction(function () use ($invoice, $data, $issue) {
            $this->fill($invoice, $data);
            if ($issue) {
                $invoice->invoice_status = 'Sent';
            }
            $invoice->save();
            InvoiceItem::where('invoice_id', $invoice->invoice_id)->delete();
            $this->saveLines($invoice, $data);
        });

        $invoice->load(['contact', 'items', 'transactions']);
        return response()->json(['data' => $this->document($invoice, $b), 'message' => $issue ? 'Invoice issued.' : 'Invoice saved.']);
    }

    /** @return array|JsonResponse validated + calculated data, or an error response */
    private function validated(Request $request, $b)
    {
        $rules = $this->lineRules() + [
            'customer_id' => ['required', 'integer'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'terms' => ['nullable', 'string', 'max:100'],
        ];
        $v = Validator::make($request->all(), $rules, $this->lineMessages() + [
            'customer_id.required' => 'Choose a customer.',
            'issue_date.required' => 'Enter the invoice date.',
            'due_date.required' => 'Enter the due date.',
            'due_date.after_or_equal' => 'The due date cannot be before the invoice date.',
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

    private function fill(Invoice $invoice, array $d): void
    {
        $r = $d['request'];
        $t = $d['totals'];
        $invoice->sent_to = $d['customer']->contact_id;
        $invoice->invoice_date = $r->issue_date;
        $invoice->invoice_due_date = $r->due_date;
        $invoice->reference = $r->reference ?: null;
        $invoice->notes = $r->notes ?: null;
        $invoice->terms = $r->terms ?: null;
        $invoice->amounts_are = $d['mode'];
        $invoice->discount_method = $d['method'];
        $invoice->discount_type = $d['discount_value'];       // legacy column: the percentage or dollar value typed in
        $invoice->discount_amount = $t['discount'];
        $invoice->tax_rate = 0;                                 // no extra "sales tax": Australia only has GST
        $invoice->tax_amount = 0;
        $invoice->invoice_amount = $t['gross'];
        $invoice->subtotal = $t['subtotal'];
        $invoice->gst_amount = $t['gst'];
        $invoice->invoice_total = $t['total'];
    }

    private function saveLines(Invoice $invoice, array $d): void
    {
        foreach ($d['lines'] as $i => $line) {
            $calc = $d['totals']['lines'][$i];
            InvoiceItem::create([
                'invoice_id' => $invoice->invoice_id,
                'item_description' => $line['description'],
                'item_quantity' => $line['quantity'],
                'item_rate' => $line['rate'],
                'item_gst_rate' => $line['gst_rate'],
                'item_cost' => $calc['ex_gst'],
                'item_gst' => $calc['gst'],
                'item_total' => $calc['inc_gst'],
            ]);
        }
    }

    /** A tax invoice must carry the seller's ABN. */
    private function cannotIssue($b): ?string
    {
        if ($b->gst_registered && !$b->company_abn) {
            return 'Add this business\'s ABN before issuing tax invoices (Client businesses → Edit).';
        }
        return null;
    }

    // ------------------------------------------------------------------ actions

    public function issue($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $invoice = Invoice::where('company_id', $b->company_id)->find($id);
        if (!$invoice) {
            return $this->failure('Invoice not found.', 404);
        }
        if ($invoice->invoice_status !== 'Draft') {
            return $this->failure('Only a draft can be issued.');
        }
        if ($err = $this->cannotIssue($b)) {
            return $this->failure($err);
        }
        $invoice->invoice_status = 'Sent';
        $invoice->save();
        $invoice->load(['contact', 'items', 'transactions']);
        return response()->json(['data' => $this->document($invoice, $b), 'message' => 'Invoice issued.']);
    }

    public function void($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $invoice = Invoice::where('company_id', $b->company_id)->with('transactions')->find($id);
        if (!$invoice) {
            return $this->failure('Invoice not found.', 404);
        }
        if ($invoice->invoice_status === 'Draft') {
            return $this->failure('A draft can be deleted instead of voided.');
        }
        if ($invoice->invoice_status === 'Cancelled') {
            return $this->failure('This invoice is already void.');
        }
        if ($invoice->transactions->count() > 0) {
            return $this->failure('This invoice has payments recorded. Remove them before voiding.');
        }
        $invoice->invoice_status = 'Cancelled';
        $invoice->save();
        $invoice->load(['contact', 'items', 'transactions']);
        return response()->json(['data' => $this->document($invoice, $b), 'message' => 'Invoice voided.']);
    }

    public function destroy($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $invoice = Invoice::where('company_id', $b->company_id)->find($id);
        if (!$invoice) {
            return $this->failure('Invoice not found.', 404);
        }
        if ($invoice->invoice_status !== 'Draft') {
            return $this->failure('Only drafts can be deleted. Void the invoice instead so your records stay complete.');
        }
        $invoice->delete();
        return response()->json(['message' => 'Draft deleted.']);
    }

    // ------------------------------------------------------------------ payments

    public function addPayment(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $invoice = Invoice::where('company_id', $b->company_id)->with(['contact', 'items', 'transactions'])->find($id);
        if (!$invoice) {
            return $this->failure('Invoice not found.', 404);
        }
        $status = $this->statusOf($invoice, now()->toDateString());
        if (!in_array($status, ['awaiting', 'overdue'], true)) {
            return $this->failure($status === 'paid' ? 'This invoice is already paid.' : 'Payments can only be recorded on an issued invoice.');
        }
        $v = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:' . implode(',', self::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Enter the amount received.',
            'amount.gt' => 'The amount must be more than 0.',
            'date.required' => 'Enter the date it was received.',
            'date.before_or_equal' => 'The payment date cannot be in the future.',
            'method.required' => 'Choose how it was paid.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $due = round($invoice->invoice_total - $invoice->invoice_paid_amount, 2);
        $amount = round((float) $request->amount, 2);
        if ($amount > $due + 0.001) {
            return $this->failure('Please fix the highlighted fields.', 422, ['amount' => ['That is more than the $' . number_format($due, 2) . ' still owing.']]);
        }

        DB::transaction(function () use ($invoice, $request, $amount) {
            InvoiceTransaction::create([
                'invoice_id' => $invoice->invoice_id,
                'payment_amount' => $amount,
                'payment_date' => $request->date,
                'payment_method' => $request->method,
                'reference_number' => $request->reference ?: null,
                'notes' => $request->notes ?: null,
            ]);
            $this->syncPaid($invoice);
        });

        $invoice->load(['contact', 'items', 'transactions']);
        return response()->json(['data' => $this->document($invoice, $b), 'message' => 'Payment recorded.'], 201);
    }

    public function removePayment($id, $transactionId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $invoice = Invoice::where('company_id', $b->company_id)->find($id);
        $tx = $invoice ? InvoiceTransaction::where('invoice_id', $invoice->invoice_id)->where('transaction_id', $transactionId)->first() : null;
        if (!$invoice || !$tx) {
            return $this->failure('Payment not found.', 404);
        }
        DB::transaction(function () use ($invoice, $tx) {
            $tx->delete();
            $this->syncPaid($invoice);
        });
        $invoice->load(['contact', 'items', 'transactions']);
        return response()->json(['data' => $this->document($invoice, $b), 'message' => 'Payment removed.']);
    }

    private function syncPaid(Invoice $invoice): void
    {
        $paid = round((float) InvoiceTransaction::where('invoice_id', $invoice->invoice_id)->sum('payment_amount'), 2);
        $invoice->invoice_paid_amount = $paid;
        $invoice->invoice_payment_status = $paid <= 0 ? 'Unpaid' : ($paid >= (float) $invoice->invoice_total ? 'Paid' : 'Partially Paid');
        $invoice->save();
    }
}
