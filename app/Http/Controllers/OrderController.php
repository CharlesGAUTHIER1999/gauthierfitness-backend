<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Commandes', weight: 6)]
class OrderController extends Controller
{
    /**
     * Historique des commandes de l'utilisateur.
     *
     * Renvoie toutes les commandes de l'utilisateur authentifié (les plus récentes en premier),
     * avec leur paiement et livraison associés.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with([
                'items.product:id,name,slug',
                'items.option:id,label,type',
                'payment:id,order_id,provider,status,amount,created_at',
                'shipment:id,order_id,firstname,lastname,address,zip,city,country,phone,carrier,tracking_url,status',
            ])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * Détail d'une commande.
     *
     * Vérifie que la commande appartient bien à l'utilisateur authentifié.
     *
     * @response 404 scenario="Commande introuvable ou appartenant à un autre utilisateur" {}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load([
            'items.product:id,name,slug',
            'items.option:id,label,type',
            'payment:id,order_id,provider,status,amount,created_at,provider_payment_id',
            'shipment:id,order_id,firstname,lastname,address,zip,city,country,phone,carrier,tracking_url,status',
        ]);

        return response()->json($order);
    }

    /**
     * Créer une commande (déprécié).
     *
     * @deprecated Cet endpoint renvoie systématiquement 405. La création d'une commande passe par `POST /api/payment/intent` qui orchestre la création de la commande, du paiement Stripe et de la livraison en une seule transaction.
     *
     * @response 405 {"message": "Use POST /payment/intent to create an order."}
     */
    public function store(): JsonResponse
    {
        return response()->json([
            'message' => 'Use POST /payment/intent to create an order.',
        ], 405);
    }
}
