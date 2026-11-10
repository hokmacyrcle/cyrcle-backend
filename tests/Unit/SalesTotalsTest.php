<?php

namespace Tests\Unit;

use App\Services\Sales\SalesTotals;
use PHPUnit\Framework\TestCase;

class SalesTotalsTest extends TestCase
{
    public function test_exclusive_adds_ten_percent_gst(): void
    {
        $t = SalesTotals::compute([['quantity' => 2, 'rate' => 100, 'gst_rate' => 10]]);
        $this->assertSame(200.0, $t['subtotal']);
        $this->assertSame(20.0, $t['gst']);
        $this->assertSame(220.0, $t['total']);
    }

    public function test_inclusive_works_gst_out_of_the_price(): void
    {
        $t = SalesTotals::compute([['quantity' => 1, 'rate' => 110, 'gst_rate' => 10]], 'inclusive');
        $this->assertSame(100.0, $t['subtotal']);
        $this->assertSame(10.0, $t['gst']);
        $this->assertSame(110.0, $t['total']);
    }

    public function test_gst_free_line_has_no_gst(): void
    {
        $t = SalesTotals::compute([
            ['quantity' => 1, 'rate' => 100, 'gst_rate' => 10],
            ['quantity' => 1, 'rate' => 50, 'gst_rate' => 0],
        ]);
        $this->assertSame(150.0, $t['subtotal']);
        $this->assertSame(10.0, $t['gst']);
        $this->assertSame(160.0, $t['total']);
    }

    public function test_no_gst_ignores_line_rates(): void
    {
        $t = SalesTotals::compute([['quantity' => 3, 'rate' => 10, 'gst_rate' => 10]], 'no_gst');
        $this->assertSame(0.0, $t['gst']);
        $this->assertSame(30.0, $t['total']);
    }

    public function test_percentage_discount_reduces_gst_too(): void
    {
        $t = SalesTotals::compute([['quantity' => 1, 'rate' => 200, 'gst_rate' => 10]], 'exclusive', 'percentage', 10);
        $this->assertSame(20.0, $t['discount']);
        $this->assertSame(180.0, $t['subtotal']);
        $this->assertSame(18.0, $t['gst']);
        $this->assertSame(198.0, $t['total']);
    }

    public function test_fixed_discount_is_shared_and_adds_up_exactly(): void
    {
        $t = SalesTotals::compute([
            ['quantity' => 1, 'rate' => 33.33, 'gst_rate' => 10],
            ['quantity' => 1, 'rate' => 33.33, 'gst_rate' => 10],
            ['quantity' => 1, 'rate' => 33.34, 'gst_rate' => 10],
        ], 'exclusive', 'fixed', 10);
        $this->assertSame(10.0, $t['discount']);
        $this->assertSame(90.0, $t['subtotal']);
        $this->assertEqualsWithDelta($t['subtotal'] + $t['gst'], $t['total'], 0.0001);
    }

    public function test_discount_never_exceeds_the_invoice(): void
    {
        $t = SalesTotals::compute([['quantity' => 1, 'rate' => 50, 'gst_rate' => 10]], 'exclusive', 'fixed', 500);
        $this->assertSame(50.0, $t['discount']);
        $this->assertSame(0.0, $t['total']);
    }

    public function test_fractional_quantity(): void
    {
        $t = SalesTotals::compute([['quantity' => 1.5, 'rate' => 120, 'gst_rate' => 10]]);
        $this->assertSame(180.0, $t['subtotal']);
        $this->assertSame(18.0, $t['gst']);
    }
}
