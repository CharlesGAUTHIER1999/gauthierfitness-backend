<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

class CartMergeService
{
    /**
     * Merges a guest cart (identified by its token) into the given user's cart,
     * then deletes the guest cart. No-op if the token is empty or unknown.
     */
    public function mergeGuestCartIntoUser(?string $guestToken, User $user): void
    {
        if (! $guestToken) {
            return;
        }

        $guestCart = Cart::where('guest_token', $guestToken)->first();
        if (! $guestCart) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

        foreach ($guestCart->items as $guestItem) {
            if ($guestItem->custom_product_session_id) {
                // Guests can never own a customization session, so this never
                // happens in practice — kept defensive in case that changes.
                $guestItem->update(['cart_id' => $userCart->id]);

                continue;
            }

            $existing = CartItem::firstOrNew([
                'cart_id' => $userCart->id,
                'product_id' => $guestItem->product_id,
                'product_option_id' => $guestItem->product_option_id,
                'custom_product_session_id' => null,
            ]);

            $existing->quantity = ((int) $existing->quantity) + (int) $guestItem->quantity;
            $existing->save();
        }

        $guestCart->delete();
    }
}
