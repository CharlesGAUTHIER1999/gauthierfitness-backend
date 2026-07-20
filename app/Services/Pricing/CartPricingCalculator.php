<?php

namespace App\Services\Pricing;

// Pure pricing math shared by cart checkout and order snapshotting
class CartPricingCalculator
{
    // Resolution order : session snapshot (customization) > option price > base product price
    public static function unitPrice(?float $session_snapshot, ?float $option_price, float $product_price): float
    {
        return $session_snapshot ?? $option_price ?? $product_price;
    }

    public static function lineTotal(float $unitPrice, int $quantity): float
    {
        return $unitPrice * $quantity;
    }

    public static function round(float $amount): float
    {
        return round($amount, 2);
    }
}
