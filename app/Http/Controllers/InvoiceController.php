<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with('order')
            ->when(!$request->user()->hasRole('super-admin'), fn ($query) => $query->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id)))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $invoices]);
    }

    public function show(Invoice $invoice)
    {
        abort_unless(request()->user()->hasRole('super-admin') || $invoice->order->user_id === request()->user()->id, 403);

        return response()->json(['status' => true, 'data' => $invoice->load('order.items.productVariant.product')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['order_id' => ['required', 'exists:orders,id']]);
        $order = Order::findOrFail($data['order_id']);

        $invoice = Invoice::firstOrCreate(
            ['order_id' => $order->id],
            [
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'subtotal_amount' => $order->subtotal_amount ?: max(0, (float) $order->total_amount - (float) $order->shipping_fee),
                'discount_amount' => $order->discount_amount,
                'shipping_fee' => $order->shipping_fee,
                'total_amount' => $order->total_amount,
                'issued_at' => now(),
            ]
        );

        return response()->json(['status' => true, 'message' => 'Invoice generated.', 'data' => $invoice], 201);
    }
}
