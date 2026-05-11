<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminStockController extends Controller
{
    /**
     * Vue globale : tous les produits avec leur stock total.
     */
    public function list(Request $request): JsonResponse
    {
        $query = Product::withSum('lots as stock_qty', 'quantity')
            ->with(['mainImage:id,product_id,url,is_main,position'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku',  'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(25));
    }

    /**
     * Détail du stock pour un produit (lots par option + stock global).
     */
    public function index(Product $product): JsonResponse
    {
        $product->load(['options' => fn ($q) =>
            $q->orderBy('position')->select('id', 'product_id', 'type', 'code', 'label', 'position')
        ]);

        // Lots sans option (stock global / produit sans variante)
        $globalLots = StockLot::where('product_id', $product->id)
            ->whereNull('product_option_id')
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->get();

        // Lots par option (FIFO : expiration la plus proche en premier)
        $optionStocks = $product->options->map(function ($option) use ($product) {
            $lots = StockLot::where('product_id', $product->id)
                ->where('product_option_id', $option->id)
                ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
                ->orderBy('id')
                ->get();

            return [
                'option_id'    => $option->id,
                'option_code'  => $option->code,
                'option_label' => $option->label,
                'option_type'  => $option->type,
                'total_qty'    => (int) $lots->sum('quantity'),
                'lots'         => $lots,
            ];
        });

        return response()->json([
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'global_stock'  => [
                'total_qty' => (int) $globalLots->sum('quantity'),
                'lots'      => $globalLots,
            ],
            'option_stocks' => $optionStocks,
        ]);
    }

    /**
     * Réapprovisionner un produit — crée un nouveau lot.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'product_option_id' => ['nullable', 'integer', 'exists:product_options,id'],
            'lot_number'        => ['required', 'string', 'max:100'],
            'quantity'          => ['required', 'integer', 'min:1'],
            'expiration_date'   => ['nullable', 'date', 'after:today'],
        ]);

        return DB::transaction(function () use ($data, $product) {
            $lot = StockLot::create([
                'product_id'        => $product->id,
                'product_option_id' => $data['product_option_id'] ?? null,
                'lot_number'        => $data['lot_number'],
                'initial_quantity'  => $data['quantity'],
                'quantity'          => $data['quantity'],
                'expiration_date'   => $data['expiration_date'] ?? null,
            ]);

            StockMovement::create([
                'lot_id'            => $lot->id,
                'product_id'        => $product->id,
                'product_option_id' => $data['product_option_id'] ?? null,
                'user_id'           => auth()->id(),
                'type'              => 'in',
                'quantity'          => $data['quantity'],
                'reason'            => 'Réapprovisionnement admin — lot ' . $lot->lot_number,
            ]);

            return response()->json($lot->load('option'), 201);
        });
    }

    /**
     * Correction manuelle de la quantité d'un lot existant.
     */
    public function adjust(Request $request, StockLot $lot): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'reason'   => ['required', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($data, $lot) {
            $delta = $data['quantity'] - $lot->quantity;

            $lot->quantity = $data['quantity'];
            $lot->save();

            StockMovement::create([
                'lot_id'            => $lot->id,
                'product_id'        => $lot->product_id,
                'product_option_id' => $lot->product_option_id,
                'user_id'           => auth()->id(),
                'type'              => 'correction',
                'quantity'          => $delta,
                'reason'            => $data['reason'],
            ]);

            return response()->json($lot->fresh()->load('option'));
        });
    }

    /**
     * Historique paginé des mouvements pour un produit.
     */
    public function movements(Product $product): JsonResponse
    {
        $movements = StockMovement::where('product_id', $product->id)
            ->with(['lot:id,lot_number,expiration_date,product_option_id'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($movements);
    }
}
