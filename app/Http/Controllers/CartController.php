<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $cart = $this->cartFor($request);

        return response()->json(['status' => true, 'data' => $cart->load('items.productVariant.product')]);
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
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $variant = ProductVariant::findOrFail($data['product_variant_id']);
        $cart = $this->cartFor($request);
        $item = $cart->items()->firstOrNew(['product_variant_id' => $variant->id]);
        $newQuantity = $item->exists ? $item->quantity + $data['quantity'] : $data['quantity'];

        if ($newQuantity > $variant->stock) {
            return response()->json(['status' => false, 'message' => 'Requested quantity exceeds available stock.'], 422);
        }

        $item->quantity = $newQuantity;
        $item->save();

        return response()->json(['status' => true, 'message' => 'Cart updated.', 'data' => $cart->fresh()->load('items.productVariant.product')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cart $cart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cart $cart)
    {
        abort_unless($cart->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $item = $cart->items()->where('product_variant_id', $data['product_variant_id'])->firstOrFail();

        if ($data['quantity'] === 0) {
            $item->delete();
        } else {
            $variant = ProductVariant::findOrFail($data['product_variant_id']);
            if ($data['quantity'] > $variant->stock) {
                return response()->json(['status' => false, 'message' => 'Requested quantity exceeds available stock.'], 422);
            }

            $item->update(['quantity' => $data['quantity']]);
        }

        return response()->json(['status' => true, 'message' => 'Cart updated.', 'data' => $cart->fresh()->load('items.productVariant.product')]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cart $cart)
    {
        abort_unless($cart->user_id === request()->user()->id, 403);

        $cart->items()->delete();

        return response()->json(['status' => true, 'message' => 'Cart cleared.']);
    }

    public function removeItem(Request $request, CartItem $cartItem)
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);

        $cartItem->delete();

        return response()->json(['status' => true, 'message' => 'Cart item removed.']);
    }

    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }
}
