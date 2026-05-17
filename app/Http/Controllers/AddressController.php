<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json(['status' => true, 'data' => $request->user()->addresses()->latest()->get()]);
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
        $data = $this->validatedData($request);

        if ($data['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($data);

        return response()->json(['status' => true, 'message' => 'Address created.', 'data' => $address], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Address $address)
    {
        abort_unless($address->user_id === request()->user()->id, 403);

        return response()->json(['status' => true, 'data' => $address]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Address $address)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $data = $this->validatedData($request, false);

        if ($data['is_default'] ?? false) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json(['status' => true, 'message' => 'Address updated.', 'data' => $address->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address)
    {
        abort_unless($address->user_id === request()->user()->id, 403);

        $address->delete();

        return response()->json(['status' => true, 'message' => 'Address deleted.']);
    }

    private function validatedData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'recipient_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone_number' => [$creating ? 'required' : 'sometimes', 'string', 'max:50'],
            'address_line_1' => [$creating ? 'required' : 'sometimes', 'string'],
            'address_line_2' => ['nullable', 'string'],
            'city' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'province' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
