<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $products = Product::query()
            ->with(['category', 'store.vendor', 'variants'])
            ->when(!$user, fn ($query) => $query->where('is_published', true))
            ->when($user && $request->has('published'), fn ($query) => $query->where('is_published', $request->boolean('published')))
            ->when($user && $user->hasRole('vendor') && !$user->hasRole('super-admin'), fn ($query) => $query->whereHas('store.vendor', fn ($query) => $query->where('user_id', $user->id)))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('meta_title', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->category_id))
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->store_id))
            ->when($request->filled('vendor_id'), fn ($query) => $query->whereHas('store', fn ($query) => $query->where('vendor_id', $request->vendor_id)))
            ->when($request->filled('min_price'), fn ($query) => $query->where('base_price', '>=', $request->min_price))
            ->when($request->filled('max_price'), fn ($query) => $query->where('base_price', '<=', $request->max_price))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $products]);
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
        $data = $this->validatedData($request);
        $this->ensureVendorOwnsStore($request, (int) $data['store_id']);

        $data['slug'] ??= Str::slug($data['name']);
        $product = Product::create($data);

        return response()->json(['status' => true, 'message' => 'Product created.', 'data' => $product->load(['category', 'store'])], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        if (!$product->is_published && !request()->user()) {
            abort(404);
        }

        return response()->json(['status' => true, 'data' => $product->load(['category', 'store.vendor', 'variants'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $data = $this->validatedData($request, $product);
        $this->ensureVendorOwnsStore($request, (int) ($data['store_id'] ?? $product->store_id));

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);

        return response()->json(['status' => true, 'message' => 'Product updated.', 'data' => $product->fresh()->load(['category', 'store'])]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->ensureVendorOwnsStore(request(), (int) $product->store_id);

        $product->delete();

        return response()->json(['status' => true, 'message' => 'Product deleted.']);
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'store_id' => [$product ? 'sometimes' : 'required', 'exists:stores,id'],
            'category_id' => [$product ? 'sometimes' : 'required', 'exists:categories,id'],
            'name' => [$product ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'description' => [$product ? 'sometimes' : 'required', 'string'],
            'base_price' => [$product ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function ensureVendorOwnsStore(Request $request, int $storeId): void
    {
        $user = $request->user();

        if (!$user || $user->hasRole('super-admin')) {
            return;
        }

        $ownsStore = Store::whereKey($storeId)->whereHas('vendor', fn ($query) => $query->where('user_id', $user->id))->exists();

        abort_unless($ownsStore, 403, 'You can only manage products for your own store.');
    }
}
