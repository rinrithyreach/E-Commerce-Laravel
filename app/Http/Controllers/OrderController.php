<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Invoice;
use App\Models\ProductVariant;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with('items.productVariant.product.store.vendor')
            ->when(!$request->user()->hasRole('super-admin'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $orders]);
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
            'shipping_address' => ['required', 'string'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string'],
        ]);

        $cart = $request->user()->cart()->with('items.productVariant.product.store.vendor')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Cart is empty.'], 422);
        }

        $order = DB::transaction(function () use ($cart, $data, $request) {
            $subtotal = 0;

            foreach ($cart->items as $item) {
                $variant = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->firstOrFail();

                if ($item->quantity > $variant->stock) {
                    abort(422, "Insufficient stock for SKU {$variant->sku}.");
                }

                $subtotal += ((float) $item->productVariant->product->base_price + (float) $item->productVariant->price_modifier) * $item->quantity;
            }

            $coupon = isset($data['coupon_code'])
                ? Coupon::where('code', Str::upper($data['coupon_code']))->first()
                : null;
            $discount = $coupon ? $coupon->calculateDiscount($subtotal) : 0;
            $shippingFee = (float) ($data['shipping_fee'] ?? 0);
            $total = $subtotal - $discount + $shippingFee;

            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'coupon_id' => $coupon?->id,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'shipping_fee' => $shippingFee,
                'shipping_address' => $data['shipping_address'],
            ]);

            if ($coupon && $discount > 0) {
                CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $request->user()->id, 'order_id' => $order->id]);
            }

            foreach ($cart->items as $item) {
                $variant = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->firstOrFail();
                $variant->decrement('stock', $item->quantity);

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'vendor_id' => $item->productVariant->product->store->vendor_id,
                    'quantity' => $item->quantity,
                    'price_at_purchase' => (float) $item->productVariant->product->base_price + (float) $item->productVariant->price_modifier,
                ]);
            }

            Shipment::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'shipping_cost' => $shippingFee,
            ]);

            Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'shipping_fee' => $shippingFee,
                'total_amount' => $total,
                'issued_at' => now(),
            ]);

            $cart->items()->delete();

            $this->notifyUser($request->user()->id, 'order.created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
            ]);

            return $order;
        });

        return response()->json(['status' => true, 'message' => 'Order placed.', 'data' => $order->load('items.productVariant.product', 'invoice', 'shipments')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $user = request()->user();

        abort_unless($user->hasRole('super-admin') || $order->user_id === $user->id, 403);

        return response()->json(['status' => true, 'data' => $order->load('items.productVariant.product.store.vendor')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        $user = $request->user();

        if ($user->hasRole('vendor') && !$user->hasRole('super-admin')) {
            $vendorId = $user->vendor?->id;
            abort_unless($vendorId && $order->items()->where('vendor_id', $vendorId)->exists(), 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:pending,paid,processing,shipped,completed,cancelled'],
        ]);

        $order->update($data);

        $this->notifyUser($order->user_id, 'order.status_updated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
        ]);

        return response()->json(['status' => true, 'message' => 'Order updated.', 'data' => $order->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return response()->json(['status' => true, 'message' => 'Order deleted.']);
    }

    public function vendorIndex(Request $request)
    {
        $vendorId = $request->user()->vendor?->id;

        abort_unless($vendorId || $request->user()->hasRole('super-admin'), 403);

        $orders = Order::with('items.productVariant.product')
            ->whereHas('items', fn ($query) => $query->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId)))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $orders]);
    }

    private function notifyUser(int $userId, string $type, array $data): void
    {
        DBFacade::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $userId,
            'data' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
