<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankAccount;
use App\Models\PayRun;
use App\Models\PayRunLine;
use App\Models\PayrollSetting;
use App\Services\Payroll\AbaFile;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/** The bank payment file for a finalised pay run: one upload to the bank pays everyone. */
class BankFileController extends Controller
{
    use SalesSupport;

    public function show(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $run = PayRun::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'finalised') {
            return $this->failure('The bank file is available once the pay run is finalised.', 422);
        }

        $s = PayrollSetting::where('company_id', $b->company_id)->first();
        $missing = [];
        if (!preg_match('/^\d{3}-?\d{3}$/', (string) $b->bank_bsb) || !preg_match('/^\d{4,9}$/', (string) $b->bank_account_number)) {
            $missing[] = 'your business bank account (BSB and account number)';
        }
        if (!$s || !preg_match('/^[A-Za-z]{3}$/', (string) $s->bank_code)) {
            $missing[] = 'your bank\'s 3 letter code, for example WBC';
        }
        if (!$s || !preg_match('/^\d{1,6}$/', (string) $s->bank_user_id)) {
            $missing[] = 'the bulk payment user ID your bank gave you';
        }
        if ($missing) {
            return $this->failure('Before you can download a bank file, add ' . implode(', ', $missing) . '. Your bank details are in Settings, and the other two are in Payroll settings.', 422);
        }

        $lines = PayRunLine::where('pay_run_id', $run->id)->orderBy('employee_name')->get();
        $banks = BankAccount::whereIn('employee_id', $lines->pluck('employee_id'))->where('is_primary_account', 1)->get()->keyBy('employee_id');

        $payments = [];
        $skipped = [];
        foreach ($lines as $l) {
            $cents = (int) round(((float) $l->net) * 100);
            if ($cents <= 0) {
                $skipped[] = ['name' => $l->employee_name, 'reason' => 'Net pay is $0.00, so there is nothing to pay.'];
                continue;
            }
            $bank = $banks->get($l->employee_id);
            $bsb = preg_replace('/\D/', '', (string) ($bank->bsb_number ?? ''));
            $acct = preg_replace('/\D/', '', (string) ($bank->account_number ?? ''));
            if (!$bank) {
                $skipped[] = ['name' => $l->employee_name, 'reason' => 'No bank account on file. Pay them another way, or add their bank details and download again.'];
            } elseif (strlen($bsb) !== 6) {
                $skipped[] = ['name' => $l->employee_name, 'reason' => 'Their BSB is not 6 digits.'];
            } elseif (strlen($acct) < 4 || strlen($acct) > 9) {
                $skipped[] = ['name' => $l->employee_name, 'reason' => 'Their account number must be 4 to 9 digits.'];
            } else {
                $payments[] = [
                    'bsb' => $bsb, 'account' => $acct, 'cents' => $cents,
                    'name' => $bank->account_name ?: $l->employee_name, 'reference' => 'Payslip ' . $run->reference,
                ];
            }
        }
        if (!$payments) {
            return $this->failure('No employees in this pay run can be paid by bank file yet. ' . ($skipped ? 'Check the bank details of: ' . implode(', ', array_column($skipped, 'name')) . '.' : ''), 422);
        }

        $content = AbaFile::build([
            'name' => $b->bank_account_name ?: $b->company_name, 'bsb' => $b->bank_bsb, 'account' => $b->bank_account_number,
            'bank_code' => $s->bank_code, 'user_id' => $s->bank_user_id,
        ], $payments, 'Payroll', Carbon::parse($run->pay_date), (bool) $s->bank_balancing);

        return response()->json(['data' => [
            'filename' => 'payroll-' . strtolower($run->reference) . '-' . Carbon::parse($run->pay_date)->format('Ymd') . '.aba',
            'content' => $content,
            'payments' => count($payments),
            'total' => array_sum(array_column($payments, 'cents')) / 100,
            'pay_date' => Carbon::parse($run->pay_date)->toDateString(),
            'skipped' => $skipped,
        ]]);
    }
}
