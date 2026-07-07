<?php

namespace App\Services\Pricing;

/** Pure pricing math shared by cart checkout and order snapshotting — no DB, no HTTP. */
class CartPricingCalculator
{
    /** Resolution order: session snapshot (customization) > option price > base product price. */
    public static function unitPrice(?float $sessionSnapshot, ?float $optionPrice, float $productPrice): float
    {
        return (float) ($sessionSnapshot ?? $optionPrice ?? $productPrice);
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
