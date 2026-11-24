<?php

namespace App\Http\Controllers\Api\Sales\Concerns;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

/**
 * Helpers shared by the Sales controllers.
 *
 * Every Sales call works inside the business the user has chosen (the "selected company"):
 * nothing from another business can be read or changed.
 */
trait SalesSupport
{
    protected function business(): ?Company
    {
        $user = auth()->guard('api')->user() ?? request()->user();
        $companyId = $user->company_id ?? null;
        if (!$companyId) {
            return null;
        }
        return Company::where('company_id', $companyId)->first();
    }

    protected function noBusiness(): JsonResponse
    {
        return response()->json(['message' => 'Choose a business first.'], 403);
    }

    protected function failure(string $message, int $code = 422, array $errors = []): JsonResponse
    {
        return response()->json(array_filter(['message' => $message, 'errors' => $errors ?: null]), $code);
    }

    protected function money($value): float
    {
        return round((float) $value, 2);
    }

    /** Name shown for a customer. */
    protected function contactName(?Contact $c): string
    {
        if (!$c) {
            return 'Unknown customer';
        }
        $person = trim(($c->contact_first_name ?? '') . ' ' . ($c->contact_last_name ?? ''));
        if ($c->contact_type === 'Company') {
            return $c->contact_company_name ?: ($person ?: 'Unnamed company');
        }
        if ($c->contact_type === 'Sole Trader') {
            return $c->contact_company_name ?: ($person ?: 'Unnamed sole trader');
        }
        return $person ?: ($c->contact_company_name ?: 'Unnamed customer');
    }

    /** Address lines for a customer, ready to print. */
    protected function contactAddress(?Contact $c): array
    {
        if (!$c) {
            return [];
        }
        $locality = trim(implode(' ', array_filter([$c->contact_city, $c->contact_state, $c->contact_post_code])));
        return array_values(array_filter([
            $c->contact_address_line_1,
            $c->contact_address_line_2,
            $locality,
        ]));
    }

    protected function presentCustomer(?Contact $c): ?array
    {
        if (!$c) {
            return null;
        }
        return [
            'id' => $c->contact_id,
            'name' => $this->contactName($c),
            'type' => $c->contact_type,
            'abn' => $c->contact_abn,
            'email' => $c->contact_email_address,
            'phone' => $c->contact_phone_number ?: $c->contact_mobile_number,
            'address' => $this->contactAddress($c),
        ];
    }

    /** The business issuing the document: name, ABN, address and where to pay. */
    protected function presentSeller(Company $b): array
    {
        $locality = trim(implode(' ', array_filter([$b->suburb, $b->state, $b->postcode])));
        $hasBank = $b->bank_bsb && $b->bank_account_number;
        return [
            'name' => $b->company_name,
            'abn' => $b->company_abn,
            'gst_registered' => (bool) $b->gst_registered,
            'address' => array_values(array_filter([$b->address_line, $locality])),
            'bank' => $hasBank ? [
                'account_name' => $b->bank_account_name ?: $b->company_name,
                'bsb' => $b->bank_bsb,
                'account_number' => $b->bank_account_number,
            ] : null,
        ];
    }

    /** Validation rules for the lines + totals part, shared by invoices and quotes. */
    protected function lineRules(): array
    {
        return [
            'amounts_are' => ['nullable', 'in:exclusive,inclusive,no_gst'],
            'discount_method' => ['nullable', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999.99', $this->twoDecimals('Quantity')],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999.99', $this->twoDecimals('Price')],
            'lines.*.gst_rate' => ['nullable', 'in:0,10'],
        ];
    }

    /** Amounts are stored to the cent, so more than two decimal places is refused rather than silently rounded. */
    protected function twoDecimals(string $what): \Closure
    {
        return function ($attribute, $value, $fail) use ($what) {
            if (is_numeric($value) && abs($value * 100 - round($value * 100)) > 1e-6) {
                $fail($what . ' can have at most 2 decimal places.');
            }
        };
    }

    /** True when the lines add up to more than we can store. */
    protected function tooLarge(array $lines): bool
    {
        $sum = 0.0;
        foreach ($lines as $l) {
            $sum += ((float) $l['quantity']) * ((float) $l['rate']);
        }
        return $sum > \App\Services\Sales\SalesTotals::MAX_TOTAL;
    }

    protected function lineMessages(): array
    {
        return [
            'lines.required' => 'Add at least one line.',
            'lines.min' => 'Add at least one line.',
            'lines.*.description.required' => 'Each line needs a description.',
            'lines.*.quantity.gt' => 'Quantity must be more than 0.',
            'lines.*.quantity.required' => 'Each line needs a quantity.',
            'lines.*.rate.required' => 'Each line needs a price.',
            'lines.*.gst_rate.in' => 'GST must be 10% or GST-free.',
            'discount_value.min' => 'Discount cannot be negative.',
        ];
    }

    /** A business that is not registered for GST cannot charge it. */
    protected function amountsAre(Company $b, ?string $requested): string
    {
        if (!$b->gst_registered) {
            return 'no_gst';
        }
        return $requested ?: 'exclusive';
    }

    /** Next running number for a business, e.g. INV-0007. Includes deleted rows so a number is never reused. */
    protected function nextNumber(string $table, string $column, int $companyId, string $prefix): string
    {
        $last = \DB::table($table)
            ->where('company_id', $companyId)
            ->where($column, 'like', $prefix . '-%')
            ->orderByRaw('LENGTH(' . $column . ') DESC')
            ->orderBy($column, 'desc')
            ->lockForUpdate()
            ->value($column);
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return $prefix . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
