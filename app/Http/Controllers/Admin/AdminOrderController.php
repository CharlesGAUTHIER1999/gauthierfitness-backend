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

#[Group(name: 'Admin - Commandes', weight: 11)]
class AdminOrderController extends Controller
{
    /** Dashboard - global statistics */
    public function stats(): JsonResponse
    {
        $lowThreshold = 5;

        // Stock per active product
        $stockByProduct = StockLot::selectRaw('product_id, SUM(quantity) as total_qty')->groupBy('product_id')->pluck('total_qty', 'product_id');
        $activeProductIds = Product::where('is_active', true)->pluck('id');

        $outOfStock = $activeProductIds->filter(
            fn ($id) => ($stockByProduct[$id] ?? 0) == 0
        )->count();

        $lowStock = $activeProductIds->filter(function ($id) use ($stockByProduct, $lowThreshold) {
            $qty = $stockByProduct[$id] ?? 0;

            return $qty > 0 && $qty < $lowThreshold;
        })->count();

        return response()->json([
            'products' => [
                'total' => Product::count(),
                'active' => $activeProductIds->count(),
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
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
            ],
        ]);
    }

    /**
     * Paginated list of orders (admin).
     *
     * @queryParam status string Filtre par statut : new, processing, shipped, delivered, canceled.
     * @queryParam search string Recherche par email/nom/prénom du client.
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
                $q->where('email', 'like', "%{$search}%")->orWhere('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    /** Order detail (admin). */
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
     * Update an order's status.
     *
     * @response 200 {"message": "Status updated", "order": {}}
     * @response 200 scenario="Aucun changement" {"message": "Status unchanged", "order": {}}
     *
     * @throws Throwable
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'order_status' => ['required', 'in:new,processing,shipped,delivered,canceled'],
        ]);

        $newStatus = $data['order_status'];

        return DB::transaction(function () use ($order, $newStatus) {
            if ($order->order_status === $newStatus) {
                return response()->json(['message' => 'Status unchanged', 'order' => $order]);
            }

            $order->order_status = $newStatus;
            $order->save();

            $user = $order->user;

            if ($user) {
                if ($newStatus === 'shipped' && ! $order->shipped_email_sent_at) {
                    $user->notify(new OrderStatusUpdated($order, 'shipped'));
                    $order->shipped_email_sent_at = now();
                    $order->save();
                }

                if ($newStatus === 'delivered' && ! $order->delivered_email_sent_at) {
                    $user->notify(new OrderStatusUpdated($order, 'delivered'));
                    $order->delivered_email_sent_at = now();
                    $order->save();
                }

                if ($newStatus === 'canceled' && ! $order->canceled_email_sent_at) {
                    $user->notify(new OrderStatusUpdated($order, 'canceled'));
                    $order->canceled_email_sent_at = now();
                    $order->save();
                }
            }

            return response()->json(['message' => 'Status updated', 'order' => $order->fresh()]);
        });
    }
}
