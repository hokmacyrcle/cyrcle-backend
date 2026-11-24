<?php

namespace App\Services\Sales;

use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates the draft invoices for recurring schedules. It only ever makes DRAFTS: nobody is billed
 * until a person opens the draft, checks it and issues it.
 */
class RecurringInvoices
{
    use SalesSupport;

    /** The date of the n-th run. Counting from the start date (not from the last run) stops monthly dates drifting, e.g. 31 Jan, 28 Feb, 31 Mar. */
    public static function dateOf(string $frequency, Carbon $start, int $n): Carbon
    {
        $d = $start->copy();
        return match ($frequency) {
            'weekly' => $d->addWeeks($n),
            'fortnightly' => $d->addWeeks(2 * $n),
            'monthly' => $d->addMonthsNoOverflow($n),
            'quarterly' => $d->addMonthsNoOverflow(3 * $n),
            'yearly' => $d->addYearsNoOverflow($n),
        };
    }

    /** Creates every draft that is due on or before $today. Returns how many were created. */
    public function run(?string $today = null): int
    {
        // Dates are Australian calendar dates, while the server clock is UTC.
        $today = Carbon::parse($today ?: Carbon::now('Australia/Sydney')->toDateString())->startOfDay();
        $made = 0;
        $due = RecurringInvoice::where('active', true)->whereDate('next_date', '<=', $today->toDateString())->get();
        foreach ($due as $s) {
            try {
                if ($s->end_date && $s->next_date->gt($s->end_date)) {
                    $s->update(['active' => false]);
                    continue;
                }
                DB::transaction(function () use ($s, $today, &$made) {
                    if ($this->makeDraft($s)) {
                        $made++;
                    }
                    $this->advance($s, $today);
                });
            } catch (\Throwable $e) {
                report($e);   // one broken schedule must not stop the others
            }
        }
        return $made;
    }

    /** Moves to the next date after $today. Dates missed while the job was not running are skipped, not billed in a rush. */
    public function advance(RecurringInvoice $s, Carbon $today, bool $ran = true): void
    {
        $n = $s->runs + 1;
        $next = self::dateOf($s->frequency, $s->start_date, $n);
        while ($next->lte($today)) {
            $n++;
            $next = self::dateOf($s->frequency, $s->start_date, $n);
        }
        $s->runs = $n;
        $s->next_date = $next->toDateString();
        if ($ran) {
            $s->last_run_at = now();
        }
        if ($s->end_date && $next->gt($s->end_date)) {
            $s->active = false;
        }
        $s->save();
    }

    private function makeDraft(RecurringInvoice $s): bool
    {
        $src = Invoice::where('company_id', $s->company_id)->with('items')->find($s->source_invoice_id);
        if (!$src) {
            $s->active = false;   // the invoice it copies is gone
            $s->save();
            return false;
        }
        $gap = $src->invoice_date && $src->invoice_due_date ? max(0, $src->invoice_date->diffInDays($src->invoice_due_date)) : 14;

        $inv = new Invoice();
        $inv->company_id = $s->company_id;
        $inv->invoice_number = $this->nextNumber('invoices', 'invoice_number', (int) $s->company_id, 'INV');
        $inv->invoice_status = 'Draft';
        $inv->invoice_payment_status = 'Unpaid';
        $inv->invoice_paid_amount = 0;
        $inv->recurring_invoice_id = $s->recurring_invoice_id;
        $inv->sent_to = $src->sent_to;
        $inv->invoice_date = $s->next_date->toDateString();
        $inv->invoice_due_date = $s->next_date->copy()->addDays($gap)->toDateString();
        foreach (['reference', 'notes', 'terms', 'amounts_are', 'discount_method', 'discount_type', 'discount_amount', 'tax_rate', 'tax_amount',
                  'invoice_amount', 'subtotal', 'gst_amount', 'invoice_total'] as $f) {
            $inv->{$f} = $src->{$f};
        }
        $inv->save();
        foreach ($src->items as $it) {
            InvoiceItem::create([
                'invoice_id' => $inv->invoice_id,
                'item_description' => $it->item_description,
                'item_quantity' => $it->item_quantity,
                'item_rate' => $it->item_rate,
                'item_gst_rate' => $it->item_gst_rate,
                'item_cost' => $it->item_cost,
                'item_gst' => $it->item_gst,
                'item_total' => $it->item_total,
            ]);
        }
        return true;
    }
}
