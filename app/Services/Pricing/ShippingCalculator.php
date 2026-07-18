<?php

namespace App\Services\Pricing;

/** Pure pricing math for delivery methods — no DB, no HTTP. Priced server-side; the client-sent method is never trusted for the amount. */
class ShippingCalculator
{
    public const METHODS = ['standard', 'express'];

    public const METHOD_LABELS = [
        'standard' => 'Standard',
        'express' => 'Express',
    ];

    private const FREE_SHIPPING_THRESHOLD = 70.0;

    private const STANDARD_COST = 4.90;

    private const EXPRESS_COST = 9.90;

    /** Cost (TTC) of the given method, given the product-only cart subtotal. Express never benefits from the free-shipping threshold. */
    public static function cost(string $method, float $productSubtotalTtc): float
    {
        if ($method === 'express') {
            return self::EXPRESS_COST;
        }

        return $productSubtotalTtc >= self::FREE_SHIPPING_THRESHOLD ? 0.0 : self::STANDARD_COST;
    }
}
