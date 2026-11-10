<?php

namespace Tests\Unit;

use App\Rules\ValidTfn;
use PHPUnit\Framework\TestCase;

class ValidTfnTest extends TestCase
{
    public function test_valid_tfns_pass(): void
    {
        $this->assertTrue(ValidTfn::check('123456782'));
        $this->assertTrue(ValidTfn::check('123 456 782'));
    }

    public function test_invalid_tfns_fail(): void
    {
        $this->assertFalse(ValidTfn::check('123456789'));
        $this->assertFalse(ValidTfn::check('12345678'));
        $this->assertFalse(ValidTfn::check('abcdefghi'));
        $this->assertFalse(ValidTfn::check(''));
    }
}
