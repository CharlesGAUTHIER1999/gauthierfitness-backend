<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLot;
use App\Notifications\OrderStatusUpdated;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Group(name: 'Admin - Orders', weight: 11)]
class AdminOrderController extends Controller
{
    /** Maps status to order column tracking whether its notification email was already sent. */
    private const array EMAIL_SENT_AT_FIELDS = [
        'shipped' => 'shipped_email_sent_at',
        'delivered' => 'delivered_email_sent_at',
        'canceled' => 'canceled_email_sent_at',
    ];

    /** Dashboard - global statistics */
    public function stats(): JsonResponse
    {
        $low_threshold = 5;

        // Stock per active product
        $stocky_by_product = StockLot::selectRaw('product_id, SUM(quantity) as total_qty')->groupBy('product_id')->pluck('total_qty', 'product_id');
        $active_product_ids = Product::where('is_active', true)->pluck('id');
        $out_of_stock = $active_product_ids->filter(fn ($id) => ($stocky_by_product[$id] ?? 0) == 0)->count();

        $low_stock = $active_product_ids->filter(function ($id) use ($stocky_by_product, $low_threshold) {
            $quantity = $stocky_by_product[$id] ?? 0;

            return $quantity > 0 && $quantity < $low_threshold;
        })->count();

        return response()->json([
            'products' => [
                'total' => Product::count(),
                'active' => $active_product_ids->count(),
                'customizable' => Product::where('is_customizable', true)->count(),
            ],
            'orders' => [
                'total' => Order::count(),
                'this_week' => Order::where('created_at', '>=', now()->startOfWeek())->count(),
                'by_status' => Order::selectRaw('order_status, count(*) as count')->groupBy('order_status')->pluck('count', 'order_status'),
                'revenue' => (float) Order::where('payment_status', 'paid')->sum('total_ttc'),
                'revenue_month' => (float) Order::where('payment_status', 'paid')->where('created_at', '>=', now()->startOfMonth())->sum('total_ttc'),
            ],
            'stock' => [
                'out_of_stock' => $out_of_stock,
                'low_stock' => $low_stock,
            ],
        ]);
    }

    /**
     * Paginated list of orders (admin)
     *
     * @queryParam status string Filter by status
     * @queryParam search string Search by customer
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with([
            'user:id,firstname,lastname,email',
            'payment:id,order_id,amount,status',
        ])->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('order_status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%$search%")->orWhere('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%");
            });
        }

        return response()->json($query->paginate(20));
    }

    // Order detail (admin)
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'user:id,firstname,lastname,email,phone',
            'items',
            'items.product:id,name,slug',
            'items.product.mainImage:id,product_id,url,is_main',
            'payment',
            'shipment',
        ]);

        return response()->json($order);
    }

    /**
     * Update an order's status
     *
     * @response 200 {"message": "Status updated", "order": {}}
     * @response 200 scenario="No change" {"message": "Status unchanged", "order": {}}
     *
     * @throws Throwable
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['order_status' => ['required', 'in:new,processing,shipped,delivered,canceled']]);
        $new_status = $data['order_status'];

        return DB::transaction(function () use ($order, $new_status) {
            if ($order->order_status === $new_status) {
                return response()->json(['message' => 'Status unchanged', 'order' => $order]);
            }
            $order->order_status = $new_status;
            $order->save();
            $user = $order->user;
            $email_field = self::EMAIL_SENT_AT_FIELDS[$new_status] ?? null;

            if ($user && $email_field && ! $order->$email_field) {
                $user->notify(new OrderStatusUpdated($order, $new_status));
                $order->$email_field = now();
                $order->save();
            }

            return response()->json(['message' => 'Status updated', 'order' => $order->fresh()]);
        });
    }
}
