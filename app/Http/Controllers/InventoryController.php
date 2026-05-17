<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $movements = Inventory::query()
            ->with('productVariant.product.store.vendor')
            ->when($user && $user->hasRole('vendor') && !$user->hasRole('super-admin'), fn ($query) => $query->whereHas('productVariant.product.store.vendor', fn ($query) => $query->where('user_id', $user->id)))
            ->when($request->filled('product_variant_id'), fn ($query) => $query->where('product_variant_id', $request->product_variant_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $movements]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureVendorOwnsVariant($request, (int) $data['product_variant_id']);

        $inventory = DB::transaction(function () use ($data) {
            $variant = ProductVariant::whereKey($data['product_variant_id'])->lockForUpdate()->firstOrFail();

            if ($variant->stock + $data['quantity_change'] < 0) {
                abort(422, 'Inventory movement would make stock negative.');
            }

            $variant->increment('stock', $data['quantity_change']);

            return Inventory::create($data);
        });

        return response()->json(['status' => true, 'message' => 'Inventory movement recorded.', 'data' => $inventory->load('productVariant')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Inventory $inventory)
    {
        $this->ensureVendorOwnsVariant(request(), (int) $inventory->product_variant_id);

        return response()->json(['status' => true, 'data' => $inventory->load('productVariant.product')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Inventory $inventory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Inventory $inventory)
    {
        return response()->json(['status' => false, 'message' => 'Inventory movements are immutable. Create a reversing movement instead.'], 405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inventory $inventory)
    {
        return response()->json(['status' => false, 'message' => 'Inventory movements are immutable. Create a reversing movement instead.'], 405);
    }

    private function ensureVendorOwnsVariant(Request $request, int $variantId): void
    {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            return;
        }

        $ownsVariant = ProductVariant::whereKey($variantId)
            ->whereHas('product.store.vendor', fn ($query) => $query->where('user_id', $user->id))
            ->exists();

        abort_unless($ownsVariant, 403, 'You can only manage inventory for your own variants.');
    }
}
