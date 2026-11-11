<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per business. A business with no row uses the defaults below. */
class PayrollSetting extends Model
{
    public const MIN_SUPER_RATE = 12.00;   // the super guarantee: employers cannot pay less
    public const DEFAULTS = ['super_rate' => 12.00, 'default_pay_frequency' => 'Fortnightly', 'default_hours_per_week' => 38.00, 'casual_loading' => 25.00,
        'bank_code' => null, 'bank_user_id' => null, 'bank_balancing' => true];

    protected $guarded = [];
    protected $casts = ['super_rate' => 'float', 'default_hours_per_week' => 'float', 'casual_loading' => 'float', 'bank_balancing' => 'boolean'];

    public static function forCompany(int $companyId): array
    {
        $row = static::where('company_id', $companyId)->first();
        return $row
            ? ['super_rate' => (float) $row->super_rate, 'default_pay_frequency' => $row->default_pay_frequency, 'default_hours_per_week' => (float) $row->default_hours_per_week, 'casual_loading' => (float) ($row->casual_loading ?? 25),
                'bank_code' => $row->bank_code, 'bank_user_id' => $row->bank_user_id, 'bank_balancing' => (bool) ($row->bank_balancing ?? true)]
            : self::DEFAULTS;
    }

    /** Rate in hundredths of a percent (12.00 -> 1200) for whole-cent maths. */
    public static function rateHundredths(int $companyId): int
    {
        return (int) round(self::forCompany($companyId)['super_rate'] * 100);
    }
}
