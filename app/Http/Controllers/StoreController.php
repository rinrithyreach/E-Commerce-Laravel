<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $stores = Store::query()
            ->with('vendor.user:id,name,email')
            ->when(!$user, fn ($query) => $query->where('is_active', true))
            ->when($user && $user->hasRole('vendor') && !$user->hasRole('super-admin'), fn ($query) => $query->whereHas('vendor', fn ($query) => $query->where('user_id', $user->id)))
            ->when($request->filled('vendor_id'), fn ($query) => $query->where('vendor_id', $request->vendor_id))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->search.'%'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $stores]);
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
            'vendor_id' => ['required', 'exists:vendors,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:stores,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->ensureVendorAccess($request, (int) $data['vendor_id']);
        $data['slug'] ??= Str::slug($data['name']);

        $store = Store::create($data);

        return response()->json(['status' => true, 'message' => 'Store created.', 'data' => $store], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Store $store)
    {
        return response()->json(['status' => true, 'data' => $store->load('vendor.user:id,name,email', 'products')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Store $store)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Store $store)
    {
        $data = $request->validate([
            'vendor_id' => ['sometimes', 'exists:vendors,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->ensureVendorAccess($request, (int) ($data['vendor_id'] ?? $store->vendor_id));

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $store->update($data);

        return response()->json(['status' => true, 'message' => 'Store updated.', 'data' => $store->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        $this->ensureVendorAccess(request(), (int) $store->vendor_id);

        $store->delete();

        return response()->json(['status' => true, 'message' => 'Store deleted.']);
    }

    private function ensureVendorAccess(Request $request, int $vendorId): void
    {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            return;
        }

        abort_unless($user->vendor?->id === $vendorId, 403, 'You can only manage your own stores.');
    }
}
