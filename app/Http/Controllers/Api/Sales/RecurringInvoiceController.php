<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Services\Sales\RecurringInvoices;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Recurring invoices: repeat an existing invoice on a schedule. Each time one is due, a DRAFT copy is
 * created for a person to check and issue. Nothing is ever sent to the customer automatically.
 */
class RecurringInvoiceController extends Controller
{
    use SalesSupport;

    private function today(): Carbon
    {
        return Carbon::parse(Carbon::now('Australia/Sydney')->toDateString());
    }

    private function row(RecurringInvoice $s): array
    {
        $src = $s->source;
        return [
            'id' => $s->recurring_invoice_id,
            'source_invoice_id' => $s->source_invoice_id,
            'source_number' => optional($src)->invoice_number,
            'customer' => $src && $src->contact ? $this->contactName($src->contact) : null,
            'amount' => $src ? $this->money($src->invoice_total) : 0.0,
            'frequency' => $s->frequency,
            'frequency_label' => RecurringInvoice::FREQUENCIES[$s->frequency] ?? $s->frequency,
            'next_date' => $s->next_date->toDateString(),
            'end_date' => optional($s->end_date)->toDateString(),
            'active' => (bool) $s->active,
            'drafts_created' => Invoice::where('recurring_invoice_id', $s->recurring_invoice_id)->count(),
            'last_run_at' => optional($s->last_run_at)->toDateString(),
        ];
    }

    public function index(): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $rows = RecurringInvoice::where('company_id', $b->company_id)->with('source.contact')
            ->orderByDesc('active')->orderBy('next_date')->get();
        return response()->json(['data' => $rows->map(fn ($s) => $this->row($s))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $v = Validator::make($request->all(), [
            'invoice_id' => ['required', 'integer'],
            'frequency' => ['required', Rule::in(array_keys(RecurringInvoice::FREQUENCIES))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ], ['frequency.required' => 'Choose how often to repeat.', 'frequency.in' => 'Choose how often to repeat.']);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $src = Invoice::where('company_id', $b->company_id)->find($request->invoice_id);
        if (!$src || in_array($src->invoice_status, ['Cancelled', 'Rejected'], true)) {
            return $this->failure('That invoice cannot be repeated.', 404);
        }
        $today = $this->today();
        // Without a start date the first copy comes one period after the invoice date (or the next period after today).
        $start = $request->start_date ? Carbon::parse($request->start_date) : Carbon::parse($src->invoice_date ?: $today);
        $n = $request->start_date ? 0 : 1;
        $next = RecurringInvoices::dateOf($request->frequency, $start, $n);
        while ($next->lte($today) && !$request->start_date) {
            $n++;
            $next = RecurringInvoices::dateOf($request->frequency, $start, $n);
        }
        if ($request->start_date && $next->lt($today)) {
            return $this->failure('Please fix the highlighted fields.', 422, ['start_date' => ['The start date cannot be in the past.']]);
        }
        if ($request->end_date && Carbon::parse($request->end_date)->lt($next)) {
            return $this->failure('Please fix the highlighted fields.', 422, ['end_date' => ['The end date must be after the first repeat date.']]);
        }

        $s = RecurringInvoice::create([
            'company_id' => $b->company_id,
            'source_invoice_id' => $src->invoice_id,
            'frequency' => $request->frequency,
            'start_date' => $start->toDateString(),
            'runs' => $n,
            'next_date' => $next->toDateString(),
            'end_date' => $request->end_date ?: null,
            'active' => true,
        ]);
        return response()->json(['data' => $this->row($s->load('source.contact')), 'message' => 'Repeat set up. Drafts will be created for you to check and issue.'], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $s = RecurringInvoice::where('company_id', $b->company_id)->find($id);
        if (!$s) {
            return $this->failure('Repeat not found.', 404);
        }
        $v = Validator::make($request->all(), [
            'active' => ['sometimes', 'boolean'],
            'frequency' => ['sometimes', Rule::in(array_keys(RecurringInvoice::FREQUENCIES))],
            'end_date' => ['sometimes', 'nullable', 'date'],
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        if ($request->has('frequency') && $request->frequency !== $s->frequency) {
            // A new rhythm starts from the next scheduled date.
            $s->frequency = $request->frequency;
            $s->start_date = $s->next_date->toDateString();
            $s->runs = 0;
        }
        if ($request->has('end_date')) {
            $s->end_date = $request->end_date ?: null;
        }
        if ($request->has('active')) {
            $s->active = (bool) $request->active;
            if ($s->active && $s->next_date->lte($this->today())) {
                // Resuming after a pause: carry on from the next date after today, do not bill the gap.
                (new RecurringInvoices())->advance($s, $this->today(), false);
                $s->active = !($s->end_date && $s->next_date->gt($s->end_date));
            }
        }
        $s->save();
        return response()->json(['data' => $this->row($s->load('source.contact')), 'message' => 'Saved.']);
    }

    public function destroy($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $s = RecurringInvoice::where('company_id', $b->company_id)->find($id);
        if (!$s) {
            return $this->failure('Repeat not found.', 404);
        }
        $s->delete();   // drafts it already created stay as they are
        return response()->json(['message' => 'Repeat stopped. Invoices already created are not affected.']);
    }
}
