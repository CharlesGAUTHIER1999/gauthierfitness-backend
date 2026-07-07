<?php

namespace Tests\Unit\Services\Stock;

use App\Services\Stock\StockAllocator;
use PHPUnit\Framework\TestCase;

class StockAllocatorTest extends TestCase
{
    public function test_deducts_the_requested_quantity_when_lot_has_enough_stock(): void
    {
        $this->assertSame(3, StockAllocator::deduction(50, 3));
    }

    public function test_caps_the_deduction_to_what_is_available_in_the_lot(): void
    {
        $this->assertSame(5, StockAllocator::deduction(5, 10));
    }

    public function test_deducts_nothing_when_the_lot_is_empty(): void
    {
        $this->assertSame(0, StockAllocator::deduction(0, 4));
    }

    public function test_deducts_nothing_when_nothing_is_requested(): void
    {
        $this->assertSame(0, StockAllocator::deduction(20, 0));
    }
}
