<?php

namespace App\Services\Banking;

use App\Models\ChartAccount;

/**
 * The rules of the chart of accounts and the standard chart every business starts with.
 * The starting chart is a template for an Australian small business. An accountant should review it.
 */
class ChartDefaults
{
    /** type => [label, detail types] */
    public const TYPES = [
        'asset' => ['Assets', ['Bank and cash', 'Accounts receivable', 'Current asset', 'Fixed asset', 'Non-current asset']],
        'liability' => ['Liabilities', ['Accounts payable', 'Credit card', 'Current liability', 'Non-current liability']],
        'equity' => ['Equity', ['Equity']],
        'income' => ['Income', ['Sales', 'Other income']],
        'expense' => ['Expenses', ['Cost of sales', 'Operating expense', 'Other expense']],
    ];

    /** key => [label, rate shown] */
    public const GST = [
        'gst' => ['GST (10%)', '10%'],
        'capital' => ['GST on capital purchases (10%)', '10%'],
        'free' => ['GST free', '0%'],
        'input' => ['Input taxed', '0%'],
        'none' => ['No GST (outside BAS)', '—'],
    ];

    public static function types(): array
    {
        $o = [];
        foreach (self::TYPES as $k => [$label, $details]) {
            $o[] = ['key' => $k, 'label' => $label, 'details' => $details];
        }
        return $o;
    }

    public static function gstCodes(): array
    {
        $o = [];
        foreach (self::GST as $k => [$label, $rate]) {
            $o[] = ['key' => $k, 'label' => $label, 'rate' => $rate];
        }
        return $o;
    }

    /** [code, name, type, detail, gst, locked] */
    public static function accounts(): array
    {
        return [
            ['110', 'Petty cash', 'asset', 'Bank and cash', 'none', false],
            ['120', 'Accounts receivable', 'asset', 'Accounts receivable', 'none', true],
            ['130', 'Prepayments', 'asset', 'Current asset', 'none', false],
            ['140', 'Office equipment', 'asset', 'Fixed asset', 'capital', false],
            ['145', 'Accumulated depreciation - office equipment', 'asset', 'Fixed asset', 'none', false],
            ['150', 'Motor vehicles', 'asset', 'Fixed asset', 'capital', false],
            ['155', 'Accumulated depreciation - motor vehicles', 'asset', 'Fixed asset', 'none', false],

            ['210', 'Accounts payable', 'liability', 'Accounts payable', 'none', true],
            ['220', 'GST', 'liability', 'Current liability', 'none', true],
            ['225', 'PAYG withholding payable', 'liability', 'Current liability', 'none', true],
            ['230', 'Superannuation payable', 'liability', 'Current liability', 'none', true],
            ['235', 'Wages payable', 'liability', 'Current liability', 'none', false],
            ['240', 'Income tax payable', 'liability', 'Current liability', 'none', false],
            ['250', 'Business loan', 'liability', 'Non-current liability', 'none', false],

            ['310', 'Owner contributions', 'equity', 'Equity', 'none', false],
            ['320', 'Owner drawings', 'equity', 'Equity', 'none', false],
            ['330', 'Retained earnings', 'equity', 'Equity', 'none', true],

            ['400', 'Sales', 'income', 'Sales', 'gst', true],
            ['405', 'Other income', 'income', 'Other income', 'gst', false],
            ['410', 'Interest income', 'income', 'Other income', 'input', false],

            ['500', 'Cost of goods sold', 'expense', 'Cost of sales', 'gst', false],

            ['600', 'Accounting fees', 'expense', 'Operating expense', 'gst', false],
            ['605', 'Advertising and marketing', 'expense', 'Operating expense', 'gst', false],
            ['610', 'Bank fees', 'expense', 'Operating expense', 'input', false],
            ['615', 'Cleaning', 'expense', 'Operating expense', 'gst', false],
            ['620', 'Computer and software', 'expense', 'Operating expense', 'gst', false],
            ['625', 'Consulting and contractors', 'expense', 'Operating expense', 'gst', false],
            ['630', 'Depreciation', 'expense', 'Operating expense', 'none', false],
            ['635', 'Insurance', 'expense', 'Operating expense', 'gst', false],
            ['640', 'Legal fees', 'expense', 'Operating expense', 'gst', false],
            ['645', 'Meals and entertainment', 'expense', 'Operating expense', 'gst', false],
            ['650', 'Motor vehicle expenses', 'expense', 'Operating expense', 'gst', false],
            ['655', 'Office supplies', 'expense', 'Operating expense', 'gst', false],
            ['660', 'Postage and courier', 'expense', 'Operating expense', 'gst', false],
            ['665', 'Rent', 'expense', 'Operating expense', 'gst', false],
            ['670', 'Repairs and maintenance', 'expense', 'Operating expense', 'gst', false],
            ['675', 'Subscriptions', 'expense', 'Operating expense', 'gst', false],
            ['680', 'Superannuation', 'expense', 'Operating expense', 'none', true],
            ['685', 'Telephone and internet', 'expense', 'Operating expense', 'gst', false],
            ['690', 'Training and education', 'expense', 'Operating expense', 'gst', false],
            ['695', 'Travel', 'expense', 'Operating expense', 'gst', false],
            ['700', 'Utilities', 'expense', 'Operating expense', 'gst', false],
            ['705', 'Wages and salaries', 'expense', 'Operating expense', 'none', true],
            ['710', 'Workers compensation', 'expense', 'Operating expense', 'gst', false],
            ['800', 'Interest expense', 'expense', 'Other expense', 'none', false],
        ];
    }

    /** Give a business the standard chart. Safe to call twice: existing names are skipped. */
    public static function seed(int $companyId): void
    {
        $now = now();
        $rows = [];
        foreach (self::accounts() as [$code, $name, $type, $detail, $gst, $locked]) {
            $rows[] = [
                'company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => $type, 'detail' => $detail,
                'gst_code' => $gst, 'locked' => $locked, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        ChartAccount::insertOrIgnore($rows);
    }
}
