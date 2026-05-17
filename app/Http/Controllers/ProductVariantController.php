<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductVariantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $variants = ProductVariant::query()
            ->with('product.store.vendor')
            ->when($user && $user->hasRole('vendor') && !$user->hasRole('super-admin'), fn ($query) => $query->whereHas('product.store.vendor', fn ($query) => $query->where('user_id', $user->id)))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->product_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $variants]);
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
            'product_id' => ['required', 'exists:products,id'],
            'sku' => ['required', 'string', 'max:255', 'unique:product_variants,sku'],
            'attributes' => ['required', 'array'],
            'price_modifier' => ['nullable', 'numeric'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->ensureVendorOwnsProduct($request, (int) $data['product_id']);

        $variant = ProductVariant::create($data);

        return response()->json(['status' => true, 'message' => 'Product variant created.', 'data' => $variant], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductVariant $productVariant)
    {
        return response()->json(['status' => true, 'data' => $productVariant->load('product.store.vendor')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductVariant $productVariant)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductVariant $productVariant)
    {
        $data = $request->validate([
            'product_id' => ['sometimes', 'exists:products,id'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($productVariant)],
            'attributes' => ['sometimes', 'required', 'array'],
            'price_modifier' => ['nullable', 'numeric'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->ensureVendorOwnsProduct($request, (int) ($data['product_id'] ?? $productVariant->product_id));

        $productVariant->update($data);

        return response()->json(['status' => true, 'message' => 'Product variant updated.', 'data' => $productVariant->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductVariant $productVariant)
    {
        $this->ensureVendorOwnsProduct(request(), (int) $productVariant->product_id);

        $productVariant->delete();

        return response()->json(['status' => true, 'message' => 'Product variant deleted.']);
    }

    private function ensureVendorOwnsProduct(Request $request, int $productId): void
    {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            return;
        }

        $ownsProduct = Product::whereKey($productId)
            ->whereHas('store.vendor', fn ($query) => $query->where('user_id', $user->id))
            ->exists();

        abort_unless($ownsProduct, 403, 'You can only manage variants for your own products.');
    }
}
