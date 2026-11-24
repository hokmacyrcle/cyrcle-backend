<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayCalculator as Calc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** A stateless "what would this pay be?" calculator. Saves nothing and uses the same maths as pay runs. */
class PayCalculatorController extends Controller
{
    use SalesSupport;

    private const BASES = ['gross', 'salary', 'hourly'];
    private const SITUATIONS = ['threshold', 'no_threshold', 'no_tfn_resident', 'no_tfn_foreign'];

    public function calculate(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'basis' => ['required', Rule::in(self::BASES)],
            'frequency' => ['required', Rule::in([Calc::WEEKLY, Calc::FORTNIGHTLY, Calc::MONTHLY])],
            'situation' => ['required', Rule::in(self::SITUATIONS)],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999.99', $this->twoDecimals('Amount')],
            'study_loan' => ['nullable', 'boolean'],
            'hours' => ['nullable', 'required_if:basis,hourly', 'numeric', 'min:0', 'max:744', $this->twoDecimals('Hours')],
        ], [
            'basis.required' => 'Choose what you are entering.',
            'frequency.required' => 'Choose how often they are paid.',
            'situation.required' => 'Choose the tax situation.',
            'amount.required' => 'Enter an amount.',
            'amount.numeric' => 'Enter the amount as a number, for example 5000.',
            'hours.required_if' => 'Enter the hours worked.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        $company = $this->business();
        $rate = $company ? PayrollSetting::rateHundredths((int) $company->company_id) : Calc::SUPER_RATE_HUNDREDTHS;
        $f = $d['frequency'];
        $amountC = (int) round(((float) $d['amount']) * 100);

        $grossC = match ($d['basis']) {
            'salary' => Calc::salaryForPeriodCents($amountC, $f),
            'hourly' => Calc::hourlyGrossCents((int) round(((float) $d['hours']) * 100), $amountC),
            default => $amountC,
        };
        [$scale, $foreign, $label] = match ($d['situation']) {
            'threshold' => [Calc::SCALE_THRESHOLD, false, 'Australian resident, tax-free threshold claimed (scale 2)'],
            'no_threshold' => [Calc::SCALE_NO_THRESHOLD, false, 'Australian resident, tax-free threshold not claimed (scale 1)'],
            'no_tfn_resident' => [Calc::SCALE_NO_TFN, false, 'No tax file number, resident (47%)'],
            default => [Calc::SCALE_NO_TFN, true, 'No tax file number, foreign resident (45%)'],
        };
        $taxC = Calc::withholding($grossC, $f, $scale, $foreign);
        $loanC = $request->boolean('study_loan') ? Calc::studyLoan($grossC, $f, $scale) : 0;   // part of the tax withheld
        $taxC += $loanC;

        return response()->json(['data' => [
            'gross' => $grossC / 100,
            'tax' => $taxC / 100,
            'study_loan' => $loanC / 100,
            'super' => Calc::superCents($grossC, $rate) / 100,
            'super_rate' => $rate / 100,
            'net' => ($grossC - $taxC) / 100,
            'situation_label' => $label,
            'frequency' => $f,
            'basis' => $d['basis'],
        ]]);
    }
}
