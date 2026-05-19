<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    // get all product in all stores

    public function index( Request $request){
        try{
            $searchKeyword = $request->query('search');

            $categoryId = $request->query('category_id');

            // Initialize Scout search across everything

            $scoutQuery = Product::search($searchKeyword ?? '');

            $scoutQuery->where('is_published', true)
                   ->where('is_approved', true);

            //Filter down by Category if explicitly picked by the admin

            if($categoryId){
                $scoutQuery->where('category_id', $categoryId);
            }

            //Resolve results and load dependencies

            $products = $scoutQuery->query(function ($query) {
                $query->with(['media', 'variants', 'category', 'store'])
                ->latest();
            })->paginate(10);


            return response()->json([
                'status' => true,
                'message' => 'get all products successfully',
                'products' => ProductResource::collection($products)
            ]);
        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()],422);
        }
    }

    public function disable(Product $product){
        try{
            $product->update([
                'is_published' => false,
                'is_approved' => false
            ]);

            $product->load(['variants', 'media', 'category']);

            return response()->json([
                'status' => true,
                'message' => 'Product disabled successfully',
                'product' => new ProductResource($product)
            ]);
        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()],422);
        }
    }

    public function enable(Product $product){
        try{
            $product->update([
                'is_published' => true,
                'is_approved' => true
            ]);

            $product->load(['variants', 'media', 'category']);

            return response()->json([
                'status' => true,
                'message' => 'Product enabled successfully',
                'product' => new ProductResource($product)
            ]);
        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()],422);
        }
    }

    public function getAllDisables(){
        try{
            $products = Product::with(['media','variants','category','store'])
            ->where('is_published',false)
            ->where('is_approved',false)
            ->latest()
            ->paginate(10);

            return response()->json([
                'status' => true,
                'message' => 'get all disabled products successfully',
                'products' => ProductResource::collection($products)
            ]);
        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()],422);
        }
    }
}
