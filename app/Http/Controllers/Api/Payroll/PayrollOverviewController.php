<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\EmploymentInformation;
use App\Models\PayRun;
use App\Models\PayRunLine;
use App\Models\SuperannuationFund;
use App\Models\TaxDetail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Everything the Payroll dashboard shows, from real records only. */
class PayrollOverviewController extends Controller
{
    use SalesSupport;

    public function show(): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $cid = (int) $b->company_id;
        $today = Carbon::today();

        $people = Employee::where('emp_company_id', $cid)->where('emp_status', 'Active')->get();
        $ids = $people->pluck('emp_id');
        $jobs = EmploymentInformation::whereIn('employee_id', $ids)->get()->keyBy('employee_id');
        $tax = TaxDetail::whereIn('employee_id', $ids)->get()->keyBy('employee_id');
        $banks = BankAccount::whereIn('employee_id', $ids)->pluck('employee_id')->flip();
        $funds = SuperannuationFund::whereIn('employee_id', $ids)->pluck('employee_id')->flip();

        // Employees whose details are not complete enough to pay properly.
        $attention = [];
        foreach ($people as $e) {
            $j = $jobs->get($e->emp_id);
            $t = $tax->get($e->emp_id);
            $issues = [];
            if (!$j || !$j->pay_basis || ($j->pay_basis === 'hourly' ? !$j->hourly_rate : !$j->annual_salary)) {
                $issues[] = 'No pay rate';
            }
            if (!$j || !$j->pay_frequency) {
                $issues[] = 'No pay frequency';
            }
            if (!$t || empty($t->getRawOriginal('tax_file_number'))) {
                $issues[] = 'No tax file number';
            }
            if (!isset($banks[$e->emp_id])) {
                $issues[] = 'No bank details';
            }
            if (!isset($funds[$e->emp_id])) {
                $issues[] = 'No super fund';
            }
            if ($issues) {
                $attention[] = ['employee_id' => (int) $e->emp_id, 'name' => trim($e->emp_first_name . ' ' . $e->emp_last_name), 'issues' => $issues];
            }
        }

        // Pay runs: drafts waiting, and what is due next for each pay frequency in use.
        $drafts = PayRun::where('company_id', $cid)->where('status', 'draft')->orderBy('pay_date')->get();
        $needs = [];
        if ($drafts->isNotEmpty()) {
            foreach (PayRunLine::whereIn('pay_run_id', $drafts->pluck('id'))->get() as $l) {
                if ($l->needsAttention()) {
                    $needs[$l->pay_run_id] = ($needs[$l->pay_run_id] ?? 0) + 1;
                }
            }
        }
        $draftFreqs = $drafts->pluck('frequency')->all();

        $upNext = [];
        foreach (['Weekly', 'Fortnightly', 'Monthly'] as $f) {
            $count = $people->filter(fn ($e) => ($jobs->get($e->emp_id)?->pay_frequency) === $f)->count();
            if (!$count) {
                continue;
            }
            $last = PayRun::where('company_id', $cid)->where('frequency', $f)->orderByDesc('period_end')->first();
            $row = ['frequency' => $f, 'employees' => $count, 'has_draft' => in_array($f, $draftFreqs, true), 'next_start' => null, 'next_end' => null, 'ready' => false];
            if ($last) {
                $start = Carbon::parse($last->period_end)->addDay();
                $end = match ($f) {
                    'Weekly' => $start->copy()->addDays(6),
                    'Fortnightly' => $start->copy()->addDays(13),
                    default => $start->day === 1 ? $start->copy()->endOfMonth() : $start->copy()->addMonthNoOverflow()->subDay(),
                };
                $row['next_start'] = $start->toDateString();
                $row['next_end'] = $end->toDateString();
                $row['ready'] = $end->lt($today);
            }
            $upNext[] = $row;
        }

        // Super: finalised runs where the employer has not said it was paid.
        $superOwing = PayRun::where('company_id', $cid)->where('status', 'finalised')->whereNull('super_paid_at')
            ->where('total_super', '>', 0)->orderBy('super_due_date')->get()->map(fn (PayRun $r) => [
                'run_id' => $r->id, 'reference' => $r->reference, 'amount' => $this->money($r->total_super),
                'due_date' => $r->super_due_date?->toDateString(),
                'overdue' => $r->super_due_date ? $r->super_due_date->lt($today) : false,
            ])->values();

        $fy = PayHistoryController::fyEndYear($today);
        $sum = DB::table('pay_run_lines as l')->join('pay_runs as r', 'r.id', '=', 'l.pay_run_id')
            ->where('r.company_id', $cid)->where('r.status', 'finalised')
            ->whereBetween('r.pay_date', [($fy - 1) . '-07-01', $fy . '-06-30'])
            ->selectRaw('COALESCE(SUM(l.gross),0) g, COALESCE(SUM(l.tax),0) t, COALESCE(SUM(l.super_amount),0) s, COALESCE(SUM(l.net),0) n')->first();

        return response()->json(['data' => [
            'employees' => $people->count(),
            'fy_label' => ($fy - 1) . '–' . substr((string) $fy, 2),
            'fy_totals' => ['gross' => $this->money($sum->g), 'tax' => $this->money($sum->t), 'super' => $this->money($sum->s), 'net' => $this->money($sum->n)],
            'attention' => $attention,
            'drafts' => $drafts->map(fn (PayRun $r) => [
                'id' => $r->id, 'reference' => $r->reference, 'frequency' => $r->frequency,
                'period_start' => $r->period_start->toDateString(), 'period_end' => $r->period_end->toDateString(),
                'pay_date' => $r->pay_date->toDateString(), 'needs_attention' => $needs[$r->id] ?? 0,
            ])->values(),
            'up_next' => $upNext,
            'super_owing' => $superOwing,
        ]]);
    }
}
