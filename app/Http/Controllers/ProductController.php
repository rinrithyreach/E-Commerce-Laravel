<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
{
    try {
        $searchKeyword = $request->query('search');
        $categoryId = $request->query('category_id');

        $scoutQuery = Product::search($searchKeyword ?? '');

        $scoutQuery->where('is_published', true)
                   ->where('is_approved', true);

        if ($categoryId) {
            $scoutQuery->where('category_id', (int) $categoryId);
        }

        $products = $scoutQuery->query(function ($query) {
            $query->with(['media', 'variants', 'category', 'store']);
        })->paginate(12);

        return response()->json([
            'status' => true,
            'message' => 'Catalog fetched successfully.',
            'products' => ProductResource::collection($products)
        ], 200);
    } catch (Exception $e) {
        return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
    }
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        try{
            $product = Product::with(['media','variants','category'])
            ->where('is_published',true)
            ->findOrFail($product->id);
            return response()->json([
                'status' => true,
                'message' => 'get product successfully',
                'product' => new ProductResource($product)
            ],200);

        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()],422);
        }
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }
}
