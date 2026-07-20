<?php

namespace App\Services\Stock;

// FIFO deduction math for stock lots
class StockAllocator
{
    // Quantity actually deductible from a lot
    public static function deduction(int $available_quantity, int $requested_quantity): int
    {
        return min($available_quantity, $requested_quantity);
    }
}
