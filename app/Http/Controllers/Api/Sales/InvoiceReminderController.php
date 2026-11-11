<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Services\Sales\ReminderMessage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Overdue reminders. For now the person sends the message from their own email (or copies it) and
 * we record that a reminder went out. When the app can send email, the same message is used.
 */
class InvoiceReminderController extends Controller
{
    use SalesSupport;

    private const NOT_ISSUED = ['Draft', 'Cancelled', 'Rejected'];

    private function overdueInvoice($b, $id): ?Invoice
    {
        $i = Invoice::where('company_id', $b->company_id)->with('contact')->find($id);
        if (!$i || in_array($i->invoice_status, self::NOT_ISSUED, true)) {
            return null;
        }
        return $i;
    }

    private function daysOverdue(Invoice $i): int
    {
        $today = Carbon::parse(Carbon::now('Australia/Sydney')->toDateString());
        if (!$i->invoice_due_date || (float) $i->invoice_paid_amount >= (float) $i->invoice_total || $i->invoice_due_date->gte($today)) {
            return 0;
        }
        return (int) Carbon::parse($i->invoice_due_date->toDateString())->diffInDays($today);
    }

    public function show($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$i = $this->overdueInvoice($b, $id)) {
            return $this->failure('Invoice not found.', 404);
        }
        $days = $this->daysOverdue($i);
        if ($days < 1) {
            return $this->failure('This invoice is not overdue, so there is nothing to remind about.');
        }
        $seller = $this->presentSeller($b);
        $msg = ReminderMessage::build([
            'customer' => $i->contact ? $this->contactName($i->contact) : 'there',
            'number' => $i->invoice_number ?: ('#' . $i->invoice_id),
            'amount' => max(0, (float) $i->invoice_total - (float) $i->invoice_paid_amount),
            'due' => $i->invoice_due_date->toDateString(),
            'days' => $days,
            'seller' => $seller['name'],
            'bank' => $seller['bank'],
        ]);
        $history = InvoiceReminder::where('invoice_id', $i->invoice_id)->orderByDesc('reminder_id')->get()
            ->map(fn ($r) => ['id' => $r->reminder_id, 'date' => $r->created_at->toDateString(), 'tone' => $r->tone, 'channel' => $r->channel]);

        return response()->json(['data' => $msg + [
            'to' => optional($i->contact)->contact_email_address,
            'days_overdue' => $days,
            'history' => $history,
        ]]);
    }

    public function store(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$i = $this->overdueInvoice($b, $id)) {
            return $this->failure('Invoice not found.', 404);
        }
        $days = $this->daysOverdue($i);
        if ($days < 1) {
            return $this->failure('This invoice is not overdue, so there is nothing to remind about.');
        }
        $v = Validator::make($request->all(), ['subject' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:5000']],
            ['subject.required' => 'Enter a subject.', 'body.required' => 'Enter the message.']);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $user = auth()->guard('api')->user() ?? $request->user();
        InvoiceReminder::create([
            'company_id' => $b->company_id,
            'invoice_id' => $i->invoice_id,
            'created_by' => optional($user)->id,
            'channel' => 'manual',
            'tone' => ReminderMessage::tone($days),
            'subject' => $request->subject,
            'body' => $request->body,
            'days_overdue' => $days,
        ]);
        return response()->json(['message' => 'Reminder recorded.'], 201);
    }
}
