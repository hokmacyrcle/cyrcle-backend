<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Finalised pay by employee for an Australian financial year (1 July to 30 June). */
class PayHistoryController extends Controller
{
    use SalesSupport;

    /** The year a financial year ends in: 1 Jul 2026 to 30 Jun 2027 is "2027". */
    public static function fyEndYear(Carbon $date): int
    {
        return $date->month >= 7 ? $date->year + 1 : $date->year;
    }

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $current = self::fyEndYear(Carbon::today());
        $fy = (int) $request->query('fy', $current);
        if ($fy < 2000 || $fy > $current + 1) {
            return $this->failure('Choose a valid financial year.', 422);
        }
        $from = ($fy - 1) . '-07-01';
        $to = $fy . '-06-30';

        $rows = DB::table('pay_run_lines as l')
            ->join('pay_runs as r', 'r.id', '=', 'l.pay_run_id')
            ->where('r.company_id', $b->company_id)->where('r.status', 'finalised')
            ->whereBetween('r.pay_date', [$from, $to])
            ->orderBy('r.pay_date')->orderBy('r.id')
            ->get(['l.id as line_id', 'l.employee_id', 'l.employee_name', 'l.gross', 'l.tax', 'l.super_amount', 'l.net',
                'r.id as run_id', 'r.reference', 'r.period_start', 'r.period_end', 'r.pay_date']);

        $c = fn ($v) => (int) round(((float) $v) * 100);
        $people = [];
        $tot = ['gross' => 0, 'tax' => 0, 'super' => 0, 'net' => 0, 'payslips' => 0];
        foreach ($rows as $r) {
            $p = &$people[$r->employee_id];
            $p ??= ['employee_id' => (int) $r->employee_id, 'name' => $r->employee_name, 'gross' => 0, 'tax' => 0, 'super' => 0, 'net' => 0, 'payslips' => []];
            $p['name'] = $r->employee_name;
            $p['gross'] += $c($r->gross); $p['tax'] += $c($r->tax); $p['super'] += $c($r->super_amount); $p['net'] += $c($r->net);
            $p['payslips'][] = [
                'run_id' => (int) $r->run_id, 'line_id' => (int) $r->line_id, 'reference' => $r->reference,
                'period_start' => substr($r->period_start, 0, 10), 'period_end' => substr($r->period_end, 0, 10), 'pay_date' => substr($r->pay_date, 0, 10),
                'gross' => $this->money($r->gross), 'tax' => $this->money($r->tax), 'net' => $this->money($r->net),
            ];
            $tot['gross'] += $c($r->gross); $tot['tax'] += $c($r->tax); $tot['super'] += $c($r->super_amount); $tot['net'] += $c($r->net); $tot['payslips']++;
            unset($p);
        }
        $employees = collect($people)->map(fn ($p) => [
            'employee_id' => $p['employee_id'], 'name' => $p['name'],
            'gross' => $p['gross'] / 100, 'tax' => $p['tax'] / 100, 'super' => $p['super'] / 100, 'net' => $p['net'] / 100,
            'payslips' => array_reverse($p['payslips']),
        ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $years = DB::table('pay_runs')->where('company_id', $b->company_id)->where('status', 'finalised')->pluck('pay_date')
            ->map(fn ($d) => self::fyEndYear(Carbon::parse($d)))->push($current)->unique()->sortDesc()->values();

        return response()->json(['data' => [
            'fy' => $fy, 'label' => ($fy - 1) . '–' . substr((string) $fy, 2), 'from' => $from, 'to' => $to,
            'years' => $years,
            'totals' => ['gross' => $tot['gross'] / 100, 'tax' => $tot['tax'] / 100, 'super' => $tot['super'] / 100, 'net' => $tot['net'] / 100, 'payslips' => $tot['payslips']],
            'employees' => $employees,
        ]]);
    }
}
