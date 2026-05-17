<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $refunds = Refund::with('order', 'payment')
            ->when(!$request->user()->hasRole('super-admin'), fn ($query) => $query->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id)))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $refunds]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::with('payments')->findOrFail($data['order_id']);
        abort_unless($request->user()->hasRole('super-admin') || $order->user_id === $request->user()->id, 403);
        abort_unless((float) $data['amount'] <= (float) $order->total_amount, 422, 'Refund exceeds order total.');

        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_id' => $order->payments()->latest()->first()?->id,
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? null,
            'status' => $request->user()->hasRole('super-admin') ? 'approved' : 'requested',
        ]);

        return response()->json(['status' => true, 'message' => 'Refund request recorded.', 'data' => $refund], 201);
    }

    public function update(Request $request, Refund $refund)
    {
        $data = $request->validate(['status' => ['required', 'in:requested,pending,approved,rejected,processed']]);
        $refund->update($data);

        return response()->json(['status' => true, 'message' => 'Refund updated.', 'data' => $refund->fresh()]);
    }
}
