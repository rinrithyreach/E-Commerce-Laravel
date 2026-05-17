<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $coupons = Coupon::query()
            ->when($request->filled('search'), fn ($query) => $query->where('code', 'like', '%'.Str::upper($request->search).'%'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $coupons]);
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
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:fixed,percent'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        $data['code'] = Str::upper($data['code']);
        $coupon = Coupon::create($data);

        return response()->json(['status' => true, 'message' => 'Coupon created.', 'data' => $coupon], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Coupon $coupon)
    {
        return response()->json(['status' => true, 'data' => $coupon->loadCount('usages')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Coupon $coupon)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Coupon $coupon)
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon)],
            'type' => ['sometimes', 'required', 'in:fixed,percent'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['sometimes', 'required', 'date'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = Str::upper($data['code']);
        }

        $coupon->update($data);

        return response()->json(['status' => true, 'message' => 'Coupon updated.', 'data' => $coupon->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json(['status' => true, 'message' => 'Coupon deleted.']);
    }

    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'subtotal_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $coupon = Coupon::where('code', Str::upper($data['code']))->first();
        $discount = $coupon ? $coupon->calculateDiscount((float) $data['subtotal_amount']) : 0;

        return response()->json([
            'status' => $discount > 0,
            'data' => ['coupon' => $coupon, 'discount_amount' => $discount],
            'message' => $discount > 0 ? 'Coupon is valid.' : 'Coupon is not valid for this order.',
        ]);
    }
}
