<?php

namespace Tests\Unit;

use App\Services\Banking\StatementParser as P;
use PHPUnit\Framework\TestCase;

class StatementParserTest extends TestCase
{
    public function test_amounts_become_cents(): void
    {
        $this->assertSame(123450, P::cents('$1,234.50'));
        $this->assertSame(-1200, P::cents('(12.00)'));
        $this->assertSame(-1200, P::cents('12.00 DR'));
        $this->assertNull(P::cents('abc'));
    }

    public function test_dates_are_day_first(): void
    {
        $this->assertSame('2026-02-01', P::date('1/2/26'));
        $this->assertSame('2026-10-31', P::date('31/10/2026'));
        $this->assertNull(P::date('31/02/2026'));
    }

    public function test_file_without_header_and_signed_amounts(): void
    {
        $csv = "31/10/2026,\"-12.50\",\"COFFEE\",\"+987.50\"\r\n30/10/2026,\"+1320.00\",\"DEPOSIT\",\"+1000.00\"\r\n";
        $i = P::inspect($csv);
        $this->assertFalse($i['has_header']);
        $r = P::parse($csv, $i['mapping'], false);
        $this->assertSame([-12.5, 1320.0], [$r['lines'][0]['amount'], $r['lines'][1]['amount']]);
    }

    public function test_debit_and_credit_columns(): void
    {
        $csv = "Date,Narrative,Debit Amount,Credit Amount,Balance\n31/10/2026,Hardware,228.75,,5000.00\n30/10/2026,Customer,,1320.00,5228.75\n";
        $i = P::inspect($csv);
        $r = P::parse($csv, $i['mapping'], true);
        $this->assertSame([-228.75, 1320.0], [$r['lines'][0]['amount'], $r['lines'][1]['amount']]);
    }

    public function test_credit_card_where_purchases_are_positive(): void
    {
        $csv = "Date;Description;Amount\n01/11/2026;AIRLINE;450.00\n02/11/2026;PAYMENT;-450.00\n";
        $i = P::inspect($csv);
        $r = P::parse($csv, $i['mapping'], true, true);
        $this->assertSame([-450.0, 450.0], [$r['lines'][0]['amount'], $r['lines'][1]['amount']]);
    }

    public function test_identical_lines_are_kept_and_reimport_matches(): void
    {
        $csv = "Date,Description,Amount\n01/11/2026,Coffee,-4.50\n01/11/2026,Coffee,-4.50\n";
        $i = P::inspect($csv);
        $a = P::parse($csv, $i['mapping'], true);
        $b = P::parse($csv, $i['mapping'], true);
        $this->assertCount(2, $a['lines']);
        $this->assertNotSame($a['lines'][0]['fingerprint'], $a['lines'][1]['fingerprint']);
        $this->assertSame($a['lines'][1]['fingerprint'], $b['lines'][1]['fingerprint']);
    }

    public function test_unreadable_lines_are_reported(): void
    {
        $csv = "Date,Description,Amount\nnot a date,X,1.00\n02/11/2026,Y,abc\n03/11/2026,Z,5.00\n";
        $i = P::inspect($csv);
        $r = P::parse($csv, $i['mapping'], true);
        $this->assertCount(1, $r['lines']);
        $this->assertCount(2, $r['errors']);
    }
}
