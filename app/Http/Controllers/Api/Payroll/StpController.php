<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\EmploymentInformation;
use App\Models\PayRun;
use App\Models\PayRunLine;
use App\Models\PayrollSetting;
use App\Models\TaxDetail;
use App\Services\Payroll\StpMapper;
use Illuminate\Http\JsonResponse;

/** Checks a pay run against what Single Touch Payroll needs. Nothing is sent to the ATO from here. */
class StpController extends Controller
{
    use SalesSupport;

    private const BASIS_NAMES = ['F' => 'Full time', 'P' => 'Part time', 'C' => 'Casual'];

    public function show(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $run = PayRun::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        $lines = PayRunLine::where('pay_run_id', $run->id)->orderBy('employee_name')->get();
        $jobs = EmploymentInformation::whereIn('employee_id', $lines->pluck('employee_id'))->get()->keyBy('employee_id');
        $taxes = TaxDetail::whereIn('employee_id', $lines->pluck('employee_id'))->get()->keyBy('employee_id');
        $weekly = PayrollSetting::forCompany((int) $b->company_id)['default_hours_per_week'];

        $runIssues = [];
        if (!preg_match('/^\d{11}$/', preg_replace('/\s+/', '', (string) $b->company_abn))) {
            $runIssues[] = ['level' => 'blocking', 'text' => 'The business needs a valid 11-digit ABN. Add it in Settings.'];
        }

        $out = $lines->map(function (PayRunLine $l) use ($jobs, $taxes, $weekly) {
            $job = $jobs->get($l->employee_id);
            $tax = $taxes->get($l->employee_id);
            $hasTfn = $tax && !empty($tax->getRawOriginal('tax_file_number'));
            $basis = StpMapper::employmentBasis($job, $weekly);
            $code = StpMapper::taxTreatmentCode($tax, $hasTfn);
            $issues = [];
            if ($basis === null) {
                $issues[] = ['level' => 'blocking', 'text' => 'Add their usual weekly hours so we can report them as full time or part time.'];
            }
            if ($code === null) {
                $issues[] = ['level' => 'blocking', 'text' => 'Working holiday maker: the ATO needs to know if your business is registered as a working holiday maker employer. We do not record that yet.'];
            }
            if (!$hasTfn) {
                $issues[] = ['level' => 'warning', 'text' => 'No tax file number. They are reported as not quoted, so ask them for a tax file number declaration.'];
            }
            return [
                'line_id' => $l->id, 'employee_id' => (int) $l->employee_id, 'employee_name' => $l->employee_name,
                'employment_basis' => $basis, 'employment_basis_name' => $basis ? self::BASIS_NAMES[$basis] : null,
                'tax_treatment_code' => $code, 'issues' => $issues,
            ];
        })->values();

        $blocking = count(array_filter($runIssues, fn ($i) => $i['level'] === 'blocking'))
            + $out->sum(fn ($x) => count(array_filter($x['issues'], fn ($i) => $i['level'] === 'blocking')));

        return response()->json(['data' => ['ready' => $blocking === 0, 'blocking' => $blocking, 'run_issues' => $runIssues, 'employees' => $out]]);
    }
}
