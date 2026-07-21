<?php

namespace App\Services\Pricing;

// Pure pricing math for delivery methods
class ShippingCalculator
{
    public const array METHODS = ['standard', 'express'];

    public const array METHOD_LABELS = ['standard' => 'Standard', 'express' => 'Express'];

    private const float FREE_SHIPPING_THRESHOLD = 70.0;

    private const float STANDARD_COST = 4.90;

    private const float EXPRESS_COST = 9.90;

    // Cost (TTC) of the given method
    public static function cost(string $method, float $product_subtotal_ttc): float
    {
        if ($method === 'express') {
            return self::EXPRESS_COST;
        }

        return $product_subtotal_ttc >= self::FREE_SHIPPING_THRESHOLD ? 0.0 : self::STANDARD_COST;
    }
}
