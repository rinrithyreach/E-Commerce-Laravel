<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Store;
use App\Models\Vendor;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorProductController extends Controller
{



    public function index( Request $request)
    {

        try {

            $vendor = Vendor::where('user_id', auth()->user()->id)->firstOrFail();

            $store = Store::where('vendor_id', $vendor->id)->firstOrFail();

            $searchKeyword = $request->query('search');
            $categoryId = $request->query('category_id');

            //Initialize Scout Search

            $scoutQuery = Product::search($searchKeyword ?? '');

            $scoutQuery->where('is_published', true)
                ->where('is_approved', true);

            //Lock search queries strictly to this vendor's store!
            $scoutQuery->where('store_id', (int) $store->id);

            if ($categoryId) {
                $scoutQuery->where('category_id', $categoryId);
            }



           $products = $scoutQuery->query(function ($query) {
            $query->with(['media', 'variants', 'category'])
            ->latest();
            })->paginate(10);

            return response()->json([
                'status' => true,
                'message' => 'get all products successfully',
                'products' => ProductResource::collection($products)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }
    public function store(StoreProductRequest $request)
    {
        try {
            $product = DB::transaction(function () use ($request) {

                //  Fetch vendor account
                $vendor = Vendor::where('user_id', $request->user()->id)->firstOrFail();
                //  Fetch store tied to vendor account
                $store = Store::where('vendor_id', $vendor->id)->firstOrFail();

                //insert product

                $product = Product::create([
                    'store_id' => $store->id,
                    'category_id' => $request->category_id,
                    'name' => $request->name,
                    'slug' => Str::slug($request->name) . '-' . Str::lower(Str::random(5)),
                    'description' => $request->description,
                    'base_price' => $request->base_price,
                    'is_published' => $request->get('is_published', true),
                    'is_approved' => true,
                    'meta_title'   => $request->meta_title ?? $request->name,
                ]);

                //Loop over and build variant configurations

                foreach ($request->variants as $variantData) {
                    $variant = $product->variants()->create([
                        'sku' => $variantData['sku'],
                        'attributes' => $variantData['attributes'],
                        'price_modifier' => $variantData['price_modifier'],
                        // 'stock'        => 0, // Managed instantly behind the scenes by your InventoryObserver!
                        'stock' => $variantData['stock'],
                    ]);

                    //Track initial audit ledger entries

                    $variant->inventories()->create([
                        'quantity_change' => $variant->stock,
                        'reason' => 'initial_stock_onboarding',
                    ]);
                }

                // add product images
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $imageFile) {

                        $product->addMedia($imageFile)->toMediaCollection('products');
                    }
                }

                return $product;
            });

            return response()->json([
                'status'  => true,
                'message' => 'Product published and live on consumer app feeds instantly!',
                'product' => new ProductResource($product->load(['variants', 'media']))
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to publish listing. Database operations rolled back securely.',
                'error'   => $e->getMessage()
            ], 422);
        }
    }


    public function show(Product $product)
    {
        try {

            $product = DB::transaction(function () use ($product) {
                $product = Product::with(['media', 'variants', 'category'])->findOrFail($product->id);
                return $product;
            });

            return response()->json([
                'status' => true,
                'message' => 'get product successfully',
                'product' => new ProductResource($product)
            ], 200);
        } catch (Exception $e) {
            return response()->json(
                [
                    'status' => true,
                    'message' => $e->getMessage()
                ],
                422
            );
        }
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        try {
            $product = DB::transaction(function () use ($request, $product) {
                $vendor = Vendor::where('user_id', $request->user()->id)->firstOrFail();
                $store = Store::where('vendor_id', $vendor->id)->firstOrFail();

                // 1. Update Parent Product Details
                $product->update([
                    'store_id' => $store->id,
                    'category_id' => $request->category_id,
                    'name' => $request->name,
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'base_price' => $request->base_price,
                    'is_published' => $request->get('is_published', true),
                    'is_approved' => true,
                    'meta_title' => $request->meta_title ?? $request->name,
                ]);

                // Track IDs to keep so we can delete removed variants later
                $keepVariantIds = [];

                // 2. Process Variants dynamically (Upserting instead of blindly creating)
                foreach ($request->variants as $variantData) {

                    $variant = $product->variants()->updateOrCreate(
                        // Match condition: looks for an existing record by ID if provided by the frontend
                        ['sku' => $variantData['sku']],
                        // Values to update or set during an insertion
                        [
                            'sku' => $variantData['sku'],
                            'attributes' => $variantData['attributes'],
                            'price_modifier' => $variantData['price_modifier'],
                            'stock' => $variantData['stock']
                        ]
                    );

                    $keepVariantIds[] = $variant->id;

                    // Track inventory ledger log only if it's a freshly added variant
                    if ($variant->wasRecentlyCreated) {
                        $variant->inventories()->create([
                            'quantity_change' => $variant->stock,
                            'reason' => 'initial_stock_onboarding',
                        ]);
                    }
                }

                // 3. (Optional) Delete variants removed from the editor by the user
                $product->variants()->whereNotIn('id', $keepVariantIds)->delete();

                // 4. Fixed Media Handling (Wipe collection once outside the loop)
                if ($request->hasFile('images')) {
                    $product->clearMediaCollection('products');

                    foreach ($request->file('images') as $imageFile) {
                        $product->addMedia($imageFile)->toMediaCollection('products');
                    }
                }

                return $product;
            });

            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully!',
                'product' => new ProductResource($product->load(['variants', 'media']))
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(Product $product)
    {
        try {
            $product->update(['is_published' => false]);

            return response()->json([
                'status'  => true,
                'message' => 'Product deleted successfully!'
            ], 200);
        } catch (Exception $e) {
            return response()->json(
                [
                    'status' => true,
                    'message' => $e->getMessage()
                ],
                422
            );
        }
    }

    public function restore(Product $product)
    {
        try {

            $product->update(['is_published' => true]);

            return response()->json([
                'status'  => true,
                'message' => 'Product listed back onto live storefront feeds successfully!',
                'product' => new ProductResource($product->load(['variants', 'media', 'category']))
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
