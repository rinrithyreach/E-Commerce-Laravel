<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $vendors = Vendor::query()
            ->with(['user:id,name,email', 'stores'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), fn ($query) => $query->where('business_name', 'like', '%'.$request->search.'%'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $vendors]);
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
            'user_id' => ['required', 'exists:users,id', 'unique:vendors,user_id'],
            'business_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255', 'unique:vendors,registration_number'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'preferred_payout_method' => ['nullable', 'in:aba,bakong,wing'],
            'aba_merchant_id' => ['nullable', 'string', 'max:255'],
            'aba_api_key' => ['nullable', 'string', 'max:255'],
            'bakong_account_id' => ['nullable', 'string', 'max:255'],
            'bakong_merchant_name' => ['nullable', 'string', 'max:255'],
            'wing_account_number' => ['nullable', 'string', 'max:255'],
        ]);

        $data['status'] = 'pending';
        $vendor = Vendor::create($data);

        return response()->json(['status' => true, 'message' => 'Vendor created.', 'data' => $vendor], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Vendor $vendor)
    {
        return response()->json(['status' => true, 'data' => $vendor->load(['user:id,name,email', 'stores.products'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vendor $vendor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'business_name' => ['sometimes', 'required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255', 'unique:vendors,registration_number,'.$vendor->id],
            'status' => ['sometimes', 'in:pending,approved,rejected'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'preferred_payout_method' => ['nullable', 'in:aba,bakong,wing'],
            'aba_merchant_id' => ['nullable', 'string', 'max:255'],
            'aba_api_key' => ['nullable', 'string', 'max:255'],
            'bakong_account_id' => ['nullable', 'string', 'max:255'],
            'bakong_merchant_name' => ['nullable', 'string', 'max:255'],
            'wing_account_number' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['status'] ?? null) === 'approved' && !$vendor->approved_at) {
            $data['approved_at'] = now();
        }

        $vendor->update($data);

        return response()->json(['status' => true, 'message' => 'Vendor updated.', 'data' => $vendor->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return response()->json(['status' => true, 'message' => 'Vendor deleted.']);
    }
}
