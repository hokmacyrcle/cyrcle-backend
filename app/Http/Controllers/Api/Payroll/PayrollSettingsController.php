<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\PayrollSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PayrollSettingsController extends Controller
{
    use SalesSupport;

    public function show(): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        return response()->json(['data' => $this->out($b->company_id)]);
    }

    public function update(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $min = PayrollSetting::MIN_SUPER_RATE;
        $v = Validator::make($request->all(), [
            'super_rate' => ['required', 'numeric', 'min:' . $min, 'max:30', $this->twoDecimals('Super rate')],
            'default_pay_frequency' => ['required', Rule::in(['Weekly', 'Fortnightly', 'Monthly'])],
            'default_hours_per_week' => ['required', 'numeric', 'min:0.01', 'max:168', $this->twoDecimals('Hours')],
            'casual_loading' => ['required', 'numeric', 'min:0', 'max:100', $this->twoDecimals('Casual loading')],
        ], [
            'super_rate.required' => 'Enter the super rate.',
            'super_rate.numeric' => 'Enter the super rate as a number, for example 12.',
            'super_rate.min' => 'The super rate cannot be lower than the ' . $min . '% super guarantee.',
            'super_rate.max' => 'That super rate looks too high. The most we allow is 30%.',
            'default_pay_frequency.required' => 'Choose a pay frequency.',
            'default_hours_per_week.required' => 'Enter the usual hours per week.',
            'default_hours_per_week.min' => 'Enter hours greater than 0.',
            'default_hours_per_week.max' => 'A week has 168 hours.',
            'casual_loading.required' => 'Enter the casual loading. Enter 0 if your casual hourly rates already include it.',
            'casual_loading.numeric' => 'Enter the casual loading as a number, for example 25.',
            'casual_loading.min' => 'The casual loading cannot be below 0.',
            'casual_loading.max' => 'The casual loading cannot be more than 100.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        PayrollSetting::updateOrCreate(['company_id' => $b->company_id], $v->validated());
        return response()->json(['data' => $this->out($b->company_id)]);
    }

    private function out(int $companyId): array
    {
        return PayrollSetting::forCompany($companyId) + ['min_super_rate' => PayrollSetting::MIN_SUPER_RATE];
    }
}
