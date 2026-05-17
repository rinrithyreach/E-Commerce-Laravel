<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $items = Wishlist::with('product.category', 'product.store')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $items]);
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
        ]);

        $item = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $data['product_id'],
        ]);

        return response()->json(['status' => true, 'message' => 'Product added to wishlist.', 'data' => $item->load('product')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Wishlist $wishlist)
    {
        abort_unless($wishlist->user_id === request()->user()->id, 403);

        return response()->json(['status' => true, 'data' => $wishlist->load('product')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(wishlist $wishlist)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Wishlist $wishlist)
    {
        abort_unless($wishlist->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $wishlist->update($data);

        return response()->json(['status' => true, 'message' => 'Wishlist updated.', 'data' => $wishlist->fresh()->load('product')]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Wishlist $wishlist)
    {
        abort_unless($wishlist->user_id === request()->user()->id, 403);

        $wishlist->delete();

        return response()->json(['status' => true, 'message' => 'Wishlist item removed.']);
    }

    public function removeProduct(Request $request, int $product)
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product)
            ->delete();

        return response()->json(['status' => true, 'message' => 'Product removed from wishlist.']);
    }
}
