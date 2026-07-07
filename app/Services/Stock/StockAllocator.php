<?php

namespace App\Services\Stock;

/** Pure FIFO deduction math for stock lots — no DB, no Eloquent. */
class StockAllocator
{
    /** Quantity actually deductible from a lot, capped at what's available. */
    public static function deduction(int $availableQuantity, int $requestedQuantity): int
    {
        return min($availableQuantity, $requestedQuantity);
    }
}
