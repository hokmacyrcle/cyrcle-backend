<?php

namespace Tests\Unit;

use App\Services\Payroll\AbaFile;
use PHPUnit\Framework\TestCase;

class AbaFileTest extends TestCase
{
    private array $sender = ['name' => 'Hokma Consulting', 'bsb' => '032-001', 'account' => '123456789', 'bank_code' => 'wbc', 'user_id' => '301500'];
    private array $pay = [
        ['bsb' => '062-000', 'account' => '12345678', 'name' => 'Rakesh Chandra T', 'cents' => 503088, 'reference' => 'Payslip PR-0001'],
        ['bsb' => '083004', 'account' => '987654', 'name' => "José O'Brien-Müller", 'cents' => 150000, 'reference' => 'Payslip PR-0001'],
    ];

    private function lines(bool $balancing): array
    {
        $f = AbaFile::build($this->sender, $this->pay, 'Payroll', new \DateTime('2026-11-03'), $balancing);
        $this->assertStringEndsWith("\r\n", $f);
        return explode("\r\n", rtrim($f, "\r\n"));
    }

    public function test_every_line_is_120_characters(): void
    {
        foreach ($this->lines(true) as $l) {
            $this->assertSame(120, strlen($l));
        }
    }

    public function test_self_balancing_file_totals(): void
    {
        $l = $this->lines(true);
        $t = end($l);
        $this->assertSame('0000000000', substr($t, 20, 10));   // net
        $this->assertSame('0000653088', substr($t, 30, 10));   // credits
        $this->assertSame('0000653088', substr($t, 40, 10));   // debits
        $this->assertSame('000003', substr($t, 74, 6));        // two payments and the balancing debit
        $this->assertSame('13', substr($l[3], 18, 2));
    }

    public function test_file_without_balancing_record(): void
    {
        $l = $this->lines(false);
        $t = end($l);
        $this->assertCount(4, $l);
        $this->assertSame('0000653088', substr($t, 20, 10));
        $this->assertSame('000002', substr($t, 74, 6));
    }

    public function test_detail_fields_and_plain_ascii(): void
    {
        $l = $this->lines(true);
        $this->assertSame('062-000', substr($l[1], 1, 7));
        $this->assertSame(' 12345678', substr($l[1], 8, 9));
        $this->assertSame('53', substr($l[1], 18, 2));
        $this->assertSame('0000503088', substr($l[1], 20, 10));
        $this->assertSame("JOSE O'BRIEN-MULLER", rtrim(substr($l[2], 30, 32)));
        $this->assertSame('083-004', substr($l[2], 1, 7));
        $this->assertSame('031126', substr($l[0], 74, 6));
    }
}
