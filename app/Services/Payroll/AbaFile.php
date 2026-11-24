<?php

namespace App\Services\Payroll;

/**
 * Builds an ABA (Cemtext) bank file, the format Australian banks accept for bulk payments.
 * Every line is exactly 120 characters and ends with CR LF. Amounts are whole cents.
 *
 * $sender   = ['name', 'bsb' (6 digits), 'account', 'bank_code' (3 letters), 'user_id' (APCA number)]
 * $payments = list of ['bsb' (6 digits), 'account' (digits), 'name', 'cents', 'reference']
 */
class AbaFile
{
    /** Payroll credit. */
    public const CREDIT = '53';
    /** Debit to the sender's own account, which balances the file for banks that need it. */
    public const DEBIT = '13';

    public static function build(array $sender, array $payments, string $description, \DateTimeInterface $processDate, bool $selfBalancing = true): string
    {
        $credits = array_sum(array_column($payments, 'cents'));
        $lines = [];

        $lines[] = '0' . self::pad('', 17) . '01' . strtoupper(self::text($sender['bank_code'], 3)) . self::pad('', 7)
            . self::pad(self::text($sender['name'], 26), 26) . self::num($sender['user_id'], 6)
            . self::pad(self::text($description, 12), 12) . $processDate->format('dmy') . self::pad('', 4) . self::pad('', 36);

        $trace = self::bsb($sender['bsb']) . self::right($sender['account'], 9);
        $remitter = self::pad(self::text($sender['name'], 16), 16);

        foreach ($payments as $p) {
            $lines[] = '1' . self::bsb($p['bsb']) . self::right($p['account'], 9) . ' ' . self::CREDIT . self::num($p['cents'], 10)
                . self::pad(self::text($p['name'], 32), 32) . self::pad(self::text($p['reference'], 18), 18) . $trace . $remitter . self::num(0, 8);
        }
        if ($selfBalancing && $payments) {
            $lines[] = '1' . $trace . ' ' . self::DEBIT . self::num($credits, 10)
                . self::pad(self::text($sender['name'], 32), 32) . self::pad(self::text($description, 18), 18) . $trace . $remitter . self::num(0, 8);
        }

        $debits = $selfBalancing && $payments ? $credits : 0;
        $lines[] = '7' . '999-999' . self::pad('', 12) . self::num(abs($credits - $debits), 10) . self::num($credits, 10) . self::num($debits, 10)
            . self::pad('', 24) . self::num(count($payments) + ($debits ? 1 : 0), 6) . self::pad('', 40);

        return implode("\r\n", $lines) . "\r\n";
    }

    /** BSB as 123-456. */
    public static function bsb(string $bsb): string
    {
        $d = preg_replace('/\D/', '', $bsb);
        return substr($d, 0, 3) . '-' . substr($d, 3, 3);
    }

    /** Plain ASCII, no control characters, cut to length. Accents are turned into plain letters. */
    public static function text(string $s, int $len): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $t = $t === false ? $s : $t;
        $t = preg_replace('/[^\x20-\x7E]/', '', $t);
        return substr(trim(preg_replace('/\s+/', ' ', strtoupper($t))), 0, $len);
    }

    private static function pad(string $s, int $len): string
    {
        return str_pad($s, $len, ' ');
    }

    /** Right aligned, space filled. */
    private static function right(string $s, int $len): string
    {
        return str_pad(substr(preg_replace('/\D/', '', $s), 0, $len), $len, ' ', STR_PAD_LEFT);
    }

    /** Right aligned, zero filled. */
    private static function num($n, int $len): string
    {
        return str_pad((string) (int) preg_replace('/\D/', '', (string) $n), $len, '0', STR_PAD_LEFT);
    }
}
