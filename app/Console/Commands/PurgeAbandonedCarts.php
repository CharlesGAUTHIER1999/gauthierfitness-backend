<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('carts:purge-abandoned')]
#[Description('Deletes carts (guest or user) with no activity for 7 days.')]
class PurgeAbandonedCarts extends Command
{
    // Deletes cart — guest or user — whose last activity is older than 7 days
    public function handle(): void
    {
        $count = Cart::where('updated_at', '<', now()->subDays(7))->count();
        Cart::where('updated_at', '<', now()->subDays(7))->delete();
        $this->info("Purged $count abandoned cart(s).");
    }
}
