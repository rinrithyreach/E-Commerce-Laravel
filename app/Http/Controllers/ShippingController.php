<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function index(Request $request)
    {
        $shipments = Shipment::with('order')
            ->when(!$request->user()->hasRole('super-admin'), fn ($query) => $query->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id)))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $shipments]);
    }

    public function update(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:pending,packed,shipped,in_transit,delivered,returned'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (($data['status'] ?? null) === 'shipped') {
            $data['shipped_at'] = now();
        }

        if (($data['status'] ?? null) === 'delivered') {
            $data['delivered_at'] = now();
        }

        $shipment->update($data);

        return response()->json(['status' => true, 'message' => 'Shipment updated.', 'data' => $shipment->fresh()]);
    }
}
