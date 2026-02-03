<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request
                ->user()
                ->orders()
                ->with(['items.product', 'items.option', 'payment', 'shipment'])
                ->latest()
                ->get()
        );
    }

    public function store(): JsonResponse
    {
        return response()->json([
            'message' => 'Use POST /payment/intent to create an order.',
        ], 405);
    }
}