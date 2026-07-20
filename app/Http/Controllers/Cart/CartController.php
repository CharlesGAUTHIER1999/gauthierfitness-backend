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
    // Retrieve the current cart

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
     * Add a product to the cart
     *
     * @response 422 scenario="Insufficient stock" {"message": "Stock insuffisant"}
     * @response 403 scenario="Customization session belongs to another user" {}
     */
    public function add(AddToCartRequest $request)
    {
        $data = $request->validated();
        $quantity = (int) ($data['quantity'] ?? 1);
        $cart = $this->resolveCart($request);
        $stock = StockLot::where('product_id', $data['product_id'])->when($data['product_option_id'] ?? null, fn ($q) => $q->where('product_option_id', $data['product_option_id']))->sum('quantity');
        if ($quantity > (int) $stock) {
            return response()->json(['message' => 'Stock insuffisant'], 422);
        }
        $custom_session_id = $data['custom_product_session_id'] ?? null;

        if ($custom_session_id) {
            $session = CustomProductSession::findOrFail($custom_session_id);
            abort_unless($this->ownsSession($request, $session), 403);
            abort_unless((int) $session->product_id === (int) $data['product_id'], 422, 'Customization session does not match product.');

            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $data['product_id'],
                'product_option_id' => $data['product_option_id'] ?? null,
                'custom_product_session_id' => $custom_session_id,
                'quantity' => $quantity,
            ]);

            $session->update(['status' => 'added_to_cart']);
        } else {
            $item = CartItem::firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $data['product_id'],
                'product_option_id' => $data['product_option_id'] ?? null,
                'custom_product_session_id' => null,
            ]);

            $item->quantity = ((int) $item->quantity) + $quantity;
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
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        $item->quantity = (int) $data['quantity'];
        $item->save();

        return $this->show($request);
    }

    /**
     * Remove a cart line
     *
     * @response 404 scenario="Item not found or belongs to another user" {}
     */
    public function destroy(Request $request, CartItem $item)
    {
        abort_unless($this->ownsCart($request, $item->cart), 404);
        if ($item->customProductSession) {
            $item->customProductSession->update(['status' => 'ready']);
        }
        $item->delete();

        return $this->show($request);
    }

    // Resolves the cart for this request
    private function resolveCart(Request $request): Cart
    {
        if ($user = $request->user('sanctum')) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }
        $guest_token = $request->header('X-Guest-Cart-Token');
        abort_if(! $guest_token, 400, 'Missing guest cart identifier');

        return Cart::firstOrCreate(['guest_token' => $guest_token]);
    }

    // Whether the current request (user or guest) owns the given cart
    private function ownsCart(Request $request, Cart $cart): bool
    {
        if ($user = $request->user('sanctum')) {
            return (int) $cart->user_id === (int) $user->id;
        }
        $guest_token = $request->header('X-Guest-Cart-Token');

        return $guest_token && $cart->guest_token === $guest_token;
    }

    // Whether the current request (user or guest) owns the given customization session
    private function ownsSession(Request $request, CustomProductSession $session): bool
    {
        if ($user = $request->user('sanctum')) {
            return (int) $session->user_id === (int) $user->id;
        }
        $guest_token = $request->header('X-Guest-Cart-Token');

        return $guest_token && $session->guest_token === $guest_token;
    }

    // Build the JSON-friendly cart representation
    private function formatCart(Cart $cart): array
    {
        $items = $cart->items->map(function ($item) {
            $product = $item->product;
            $option = $item->option;
            $custom_session = $item->customProductSession;
            $unit = $custom_session?->unit_price_snapshot ?? $option?->price_ttc ?? $product->price_ttc ?? 0;
            $variant_type = $product->group?->type;

            if (! $variant_type) {
                $cat = $product->categories?->first();
                $root = $cat?->parent?->slug ?? $cat?->slug;
                $variant_type = $root === 'nutrition' ? 'flavor' : 'color';
            }

            $variant_title = $variant_type === 'flavor' ? 'Goût' : 'Couleur';
            $variant_value = $product->color_label;

            $size_label = null;
            if ($option && $option->type === 'size') {
                $size_label = $option->label ?? $option->code;
            }

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'custom_product_session_id' => $custom_session?->id,
                'is_customized' => (bool) $custom_session,
                'name' => $product->name,
                'image' => $custom_session?->preview_image_path ? $this->publicImageUrl($custom_session->preview_image_path) : ($this->publicImageUrl($product->main_image) ?? '/placeholder.jpg'),
                'quantity' => (int) $item->quantity,
                'unit_price' => round((float) $unit, 2),
                'line_total' => round((float) $unit * (int) $item->quantity, 2),
                'variant_title' => $variant_value ? $variant_title : null,
                'variant_value' => $variant_value ?: null,
                'size' => $size_label,
                'delivery_text' => 'Délai de livraison : 4–7 jours ouvrés',
                'option' => $option ? ['id' => $option->id, 'label' => $option->label ?? $option->code, 'type' => $option->type] : null,
                'customization' => $custom_session ? ['status' => $custom_session->status, 'preview_image_path' => $this->publicImageUrl($custom_session->preview_image_path), 'configuration' => $custom_session->configuration, 'design_id' => $custom_session->design_id] : null,
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
