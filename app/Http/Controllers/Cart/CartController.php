<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomProductSession;
use App\Models\StockLot;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

#[Group(name: 'Panier', weight: 3)]
class CartController extends Controller
{
    /**
     * Retrieve the current cart — the authenticated user's cart, or a guest cart
     * identified by the X-Guest-Cart-Token header.
     */
    public function show(Request $request)
    {
        $cart = $this->resolveCart($request);

        $cart->load([
            'items.product.group',
            'items.product.categories.parent',
            'items.option',
            'items.customProductSession.design',
        ]);

        return response()->json($this->formatCart($cart));
    }

    /**
     * Add a product to the cart.
     *
     * @response 422 scenario="Insufficient stock" {"message": "Stock insuffisant"}
     * @response 403 scenario="Customization session belongs to another user" {}
     */
    public function add(AddToCartRequest $request)
    {
        $data = $request->validated();
        $qty = (int) ($data['quantity'] ?? 1);
        $cart = $this->resolveCart($request);

        $stock = StockLot::where('product_id', $data['product_id'])
            ->when($data['product_option_id'] ?? null, fn ($q) => $q->where('product_option_id', $data['product_option_id'])
            )
            ->sum('quantity');

        if ($qty > (int) $stock) {
            return response()->json(['message' => 'Stock insuffisant'], 422);
        }

        $customSessionId = $data['custom_product_session_id'] ?? null;

        if ($customSessionId) {
            $session = CustomProductSession::findOrFail($customSessionId);
            abort_unless($this->ownsSession($request, $session), 403);
            abort_unless((int) $session->product_id === (int) $data['product_id'], 422, 'Customization session does not match product.');

            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $data['product_id'],
                'product_option_id' => $data['product_option_id'] ?? null,
                'custom_product_session_id' => $customSessionId,
                'quantity' => $qty,
            ]);

            $session->update([
                'status' => 'added_to_cart',
            ]);
        } else {
            $item = CartItem::firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $data['product_id'],
                'product_option_id' => $data['product_option_id'] ?? null,
                'custom_product_session_id' => null,
            ]);

            $item->quantity = ((int) $item->quantity) + $qty;
            $item->save();
        }

        return $this->show($request);
    }

    /**
     * Update the quantity of a cart line.
     *
     * @response 404 scenario="Item not found or belongs to another user" {}
     */
    public function update(Request $request, CartItem $item)
    {
        abort_unless($this->ownsCart($request, $item->cart), 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item->quantity = (int) $data['quantity'];
        $item->save();

        return $this->show($request);
    }

    /**
     * Remove a cart line.
     *
     * @response 404 scenario="Item not found or belongs to another user" {}
     */
    public function destroy(Request $request, CartItem $item)
    {
        abort_unless($this->ownsCart($request, $item->cart), 404);

        if ($item->customProductSession) {
            $item->customProductSession->update([
                'status' => 'ready',
            ]);
        }

        $item->delete();

        return $this->show($request);
    }

    /**
     * Resolves the cart for this request: the authenticated user's cart, or a
     * guest cart keyed by the X-Guest-Cart-Token header. Aborts with 400 if
     * neither is available.
     */
    private function resolveCart(Request $request): Cart
    {
        if ($user = $request->user('sanctum')) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $guestToken = $request->header('X-Guest-Cart-Token');
        abort_if(! $guestToken, 400, 'Missing guest cart identifier');

        return Cart::firstOrCreate(['guest_token' => $guestToken]);
    }

    // Whether the current request (user or guest) owns the given cart
    private function ownsCart(Request $request, Cart $cart): bool
    {
        if ($user = $request->user('sanctum')) {
            return (int) $cart->user_id === (int) $user->id;
        }

        $guestToken = $request->header('X-Guest-Cart-Token');

        return $guestToken && $cart->guest_token === $guestToken;
    }

    // Whether the current request (user or guest) owns the given customization session
    private function ownsSession(Request $request, CustomProductSession $session): bool
    {
        if ($user = $request->user('sanctum')) {
            return (int) $session->user_id === (int) $user->id;
        }

        $guestToken = $request->header('X-Guest-Cart-Token');

        return $guestToken && $session->guest_token === $guestToken;
    }

    // Build the JSON-friendly cart representation
    private function formatCart(Cart $cart): array
    {
        $items = $cart->items->map(function ($item) {
            $product = $item->product;
            $option = $item->option;
            $customSession = $item->customProductSession;

            $unit = $customSession?->unit_price_snapshot
                ?? $option?->price_ttc
                ?? $product->price_ttc
                ?? 0;

            $variantType = $product->group?->type;

            if (! $variantType) {
                $cat = $product->categories?->first();
                $root = $cat?->parent?->slug ?? $cat?->slug;
                $variantType = $root === 'nutrition' ? 'flavor' : 'color';
            }

            $variantTitle = $variantType === 'flavor' ? 'Goût' : 'Couleur';
            $variantValue = $product->color_label;

            $sizeLabel = null;
            if ($option && $option->type === 'size') {
                $sizeLabel = $option->label ?? $option->code;
            }

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'custom_product_session_id' => $customSession?->id,
                'is_customized' => (bool) $customSession,
                'name' => $product->name,
                'image' => $customSession?->preview_image_path ? $this->publicImageUrl($customSession->preview_image_path) : ($this->publicImageUrl($product->main_image) ?? '/placeholder.jpg'),
                'quantity' => (int) $item->quantity,
                'unit_price' => round((float) $unit, 2),
                'line_total' => round((float) $unit * (int) $item->quantity, 2),
                'variant_title' => $variantValue ? $variantTitle : null,
                'variant_value' => $variantValue ?: null,
                'size' => $sizeLabel,
                'delivery_text' => 'Délai de livraison : 4–7 jours ouvrés',
                'option' => $option ? ['id' => $option->id, 'label' => $option->label ?? $option->code, 'type' => $option->type] : null,
                'customization' => $customSession ? ['status' => $customSession->status, 'preview_image_path' => $this->publicImageUrl($customSession->preview_image_path), 'configuration' => $customSession->configuration, 'design_id' => $customSession->design_id] : null,
            ];
        });

        $subtotal = (float) $items->sum('line_total');

        return [
            'items' => $items->values(),
            'count' => (int) $items->sum('quantity'),
            'subtotal' => round($subtotal, 2),
            'currency' => 'EUR',
        ];
    }

    // Resolve a stored path to a public URL
    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (Str::startsWith($path, 'storage/')) {
            return url('/'.$path);
        }

        return url('/storage/'.$path);
    }
}
