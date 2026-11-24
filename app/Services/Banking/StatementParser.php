<?php

namespace App\Services\Banking;

/**
 * Reads a bank statement CSV. Pure PHP, no framework, so it is easy to test and to change.
 * Australian banks differ a lot: some have a header row and some do not, some give one signed Amount
 * column and some give separate Debit and Credit columns, and dates are day-first.
 * Money is handled as whole cents so nothing is lost to floating point.
 */
class StatementParser
{
    /** Splits the file into rows of cells. Detects comma, semicolon or tab. */
    public static function rows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);             // byte order mark
        $csv = str_replace(["\r\n", "\r"], "\n", $csv);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        if (!$lines) {
            return [];
        }
        $first = $lines[0];
        $delim = ',';
        foreach ([',', ';', "\t"] as $d) {
            if (substr_count($first, $d) > substr_count($first, $delim)) {
                $delim = $d;
            }
        }
        return array_map(fn ($l) => array_map('trim', str_getcsv($l, $delim, '"', '')), $lines);
    }

    /** Turns "1,234.50", "-$12.00", "(12.00)", "12.00-", "12.00 DR" into cents. Returns null when it is not an amount. */
    public static function cents(?string $text): ?int
    {
        $t = strtoupper(trim((string) $text));
        if ($t === '') {
            return null;
        }
        $neg = false;
        if (preg_match('/^\((.*)\)$/', $t, $m)) {
            $neg = true;
            $t = $m[1];
        }
        if (preg_match('/\s*(DR|CR)$/', $t, $m)) {
            $neg = $neg || $m[1] === 'DR';
            $t = trim(substr($t, 0, -2));
        }
        if (substr($t, -1) === '-') {
            $neg = true;
            $t = substr($t, 0, -1);
        }
        $t = str_replace(['$', ',', ' '], '', $t);
        if ($t !== '' && $t[0] === '-') {
            $neg = true;
            $t = substr($t, 1);
        } elseif ($t !== '' && $t[0] === '+') {
            $t = substr($t, 1);
        }
        if (!preg_match('/^\d*\.?\d{0,2}$/', $t) || $t === '' || $t === '.') {
            return null;
        }
        [$dollars, $frac] = array_pad(explode('.', $t, 2), 2, '');
        $cents = ((int) ($dollars === '' ? '0' : $dollars)) * 100 + (int) str_pad($frac, 2, '0');
        return $neg ? -$cents : $cents;
    }

    /** Day-first dates: 31/10/2026, 31-10-26, 2026-10-31, 31 Oct 2026. Returns Y-m-d or null. */
    public static function date(?string $text): ?string
    {
        $t = trim((string) $text);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $t, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4}|\d{2})/', $t, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) {
                $y += 2000;
            }
        } elseif (preg_match('/^(\d{1,2})[ \-]([A-Za-z]{3})[A-Za-z]*[ \-,]+(\d{4}|\d{2})/', $t, $m)) {
            $mo = (int) (array_search(strtolower($m[2]), ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], true) + 1);
            [$d, $y] = [(int) $m[1], (int) $m[3]];
            if ($y < 100) {
                $y += 2000;
            }
        } else {
            return null;
        }
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /**
     * Looks at the file and suggests which column is which.
     * @return array{has_header:bool, headers:string[], mapping:array, sample:array, rows:int}
     */
    public static function inspect(string $csv): array
    {
        $rows = self::rows($csv);
        if (!$rows) {
            return ['has_header' => false, 'headers' => [], 'mapping' => [], 'sample' => [], 'rows' => 0];
        }
        $cols = max(array_map('count', $rows));
        $hasHeader = true;
        foreach ($rows[0] as $cell) {
            if (self::date($cell) !== null) {      // a date in the first row means it is data, not a header
                $hasHeader = false;
            }
        }
        $headers = $hasHeader ? $rows[0] : array_map(fn ($i) => 'Column ' . ($i + 1), range(0, $cols - 1));
        $data = $hasHeader ? array_slice($rows, 1) : $rows;

        $map = ['date' => null, 'description' => null, 'amount' => null, 'debit' => null, 'credit' => null, 'balance' => null];
        if ($hasHeader) {
            foreach ($headers as $i => $h) {
                $h = strtolower($h);
                if ($map['date'] === null && str_contains($h, 'date')) $map['date'] = $i;
                elseif ($map['description'] === null && preg_match('/desc|narrat|detail|particular|memo|payee|reference/', $h)) $map['description'] = $i;
                elseif ($map['balance'] === null && str_contains($h, 'balance')) $map['balance'] = $i;
                elseif ($map['debit'] === null && preg_match('/debit|withdraw|money out|paid out|spent/', $h)) $map['debit'] = $i;
                elseif ($map['credit'] === null && preg_match('/credit|deposit|money in|paid in|received/', $h)) $map['credit'] = $i;
                elseif ($map['amount'] === null && str_contains($h, 'amount')) $map['amount'] = $i;
            }
        } else {
            // No header: guess from the data. The date column is the one full of dates, the description the longest text.
            $sample = array_slice($data, 0, 20);
            for ($i = 0; $i < $cols; $i++) {
                $vals = array_column($sample, $i);
                $dates = count(array_filter($vals, fn ($v) => self::date($v) !== null));
                $nums = count(array_filter($vals, fn ($v) => self::cents($v) !== null));
                if ($map['date'] === null && $dates >= count($vals) * 0.8) { $map['date'] = $i; continue; }
                if ($nums >= count($vals) * 0.8) {
                    if ($map['amount'] === null) $map['amount'] = $i; elseif ($map['balance'] === null) $map['balance'] = $i;
                    continue;
                }
                if ($map['description'] === null) $map['description'] = $i;
            }
        }
        return [
            'has_header' => $hasHeader, 'headers' => $headers, 'mapping' => $map,
            'sample' => array_slice($data, 0, 5), 'rows' => count($data),
        ];
    }

    /**
     * Turns the file into transactions using the chosen columns.
     * $map: date, description, and either amount or debit and credit; optional balance.
     * $outflowPositive: true when the bank shows money going out as a positive number (common on credit cards).
     * @return array{lines:array, errors:array}
     */
    public static function parse(string $csv, array $map, bool $hasHeader, bool $outflowPositive = false): array
    {
        $rows = self::rows($csv);
        $data = $hasHeader ? array_slice($rows, 1) : $rows;
        $lines = [];
        $errors = [];
        $seen = [];
        foreach ($data as $n => $row) {
            $rowNo = $n + 1 + ($hasHeader ? 1 : 0);
            $date = self::date($row[$map['date']] ?? null);
            if ($date === null) {
                $errors[] = "Line {$rowNo}: the date \"" . ($row[$map['date']] ?? '') . '" could not be read.';
                continue;
            }
            if (isset($map['amount']) && $map['amount'] !== null) {
                $c = self::cents($row[$map['amount']] ?? null);
                if ($c !== null && $outflowPositive) {
                    $c = -$c;
                }
            } else {
                $debit = self::cents($row[$map['debit']] ?? null) ?? 0;
                $credit = self::cents($row[$map['credit']] ?? null) ?? 0;
                $c = (abs($debit) + 0) ? -abs($debit) : abs($credit);
                if (!$debit && !$credit) {
                    $c = null;
                }
            }
            if ($c === null) {
                $errors[] = "Line {$rowNo}: the amount could not be read.";
                continue;
            }
            $desc = trim(preg_replace('/\s+/', ' ', (string) ($row[$map['description']] ?? '')));
            $bal = isset($map['balance']) && $map['balance'] !== null ? self::cents($row[$map['balance']] ?? null) : null;

            // Two identical lines on one day are real (two coffees). Number them so both are kept, and a re-import still matches.
            $key = $date . '|' . $c . '|' . strtolower($desc) . '|' . ($bal ?? '');
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $lines[] = [
                'date' => $date, 'description' => $desc === '' ? '(no description)' : mb_substr($desc, 0, 500),
                'amount' => $c / 100.0, 'balance_after' => $bal === null ? null : $bal / 100.0,
                'fingerprint' => sha1($key . '|' . $seen[$key]),
            ];
        }
        return ['lines' => $lines, 'errors' => $errors];
    }
}
