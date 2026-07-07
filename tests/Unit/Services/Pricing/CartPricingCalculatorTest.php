<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\CartPricingCalculator;
use PHPUnit\Framework\TestCase;

class CartPricingCalculatorTest extends TestCase
{
    public function test_unit_price_prefers_session_snapshot_over_option_and_product(): void
    {
        $this->assertSame(19.90, CartPricingCalculator::unitPrice(19.90, 24.90, 29.90));
    }

    public function test_unit_price_falls_back_to_option_price_when_no_snapshot(): void
    {
        $this->assertSame(24.90, CartPricingCalculator::unitPrice(null, 24.90, 29.90));
    }

    public function test_unit_price_falls_back_to_product_price_when_no_snapshot_or_option(): void
    {
        $this->assertSame(29.90, CartPricingCalculator::unitPrice(null, null, 29.90));
    }

    public function test_line_total_multiplies_unit_price_by_quantity(): void
    {
        $this->assertSame(59.80, CartPricingCalculator::lineTotal(29.90, 2));
    }

    public function test_line_total_is_zero_for_zero_quantity(): void
    {
        $this->assertSame(0.0, CartPricingCalculator::lineTotal(29.90, 0));
    }

    public function test_round_keeps_two_decimals(): void
    {
        $this->assertSame(19.99, CartPricingCalculator::round(19.994));
        $this->assertSame(20.0, CartPricingCalculator::round(19.995));
    }
}
