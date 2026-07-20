<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

class CartMergeService
{
    // Merges guest cart (identified by its token) into the given user's cart
    public function mergeGuestCartIntoUser(?string $guestToken, User $user): void
    {
        if (! $guestToken) {
            return;
        }
        $guest_cart = Cart::where('guest_token', $guestToken)->first();
        if (! $guest_cart) {
            return;
        }
        $user_cart = Cart::firstOrCreate(['user_id' => $user->id]);

        foreach ($guest_cart->items as $guest_item) {
            if ($guest_item->custom_product_session_id) {
                $guest_item->update(['cart_id' => $user_cart->id]);

                continue;
            }

            $existing = CartItem::firstOrNew([
                'cart_id' => $user_cart->id,
                'product_id' => $guest_item->product_id,
                'product_option_id' => $guest_item->product_option_id,
                'custom_product_session_id' => null,
            ]);

            $existing->quantity = ((int) $existing->quantity) + (int) $guest_item->quantity;
            $existing->save();
        }

        $guest_cart->delete();
    }
}
