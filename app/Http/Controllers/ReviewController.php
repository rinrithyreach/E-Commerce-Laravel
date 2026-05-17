<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Order;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $reviews = Review::with('user:id,name', 'product')
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->product_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        $rating = Review::query()
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->product_id))
            ->avg('rating');

        return response()->json(['status' => true, 'data' => $reviews, 'meta' => ['average_rating' => round((float) $rating, 2)]]);
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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $verified = Order::where('user_id', $request->user()->id)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])
            ->whereHas('items.productVariant', fn ($query) => $query->where('product_id', $data['product_id']))
            ->exists();

        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $data['product_id']],
            $data + ['user_id' => $request->user()->id, 'is_verified_purchase' => $verified]
        );

        return response()->json(['status' => true, 'message' => 'Review saved.', 'data' => $review->load('user:id,name')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Review $review)
    {
        return response()->json(['status' => true, 'data' => $review->load('user:id,name', 'product')]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Review $review)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id || $request->user()->hasRole('super-admin'), 403);

        $data = $request->validate([
            'rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update($data);

        return response()->json(['status' => true, 'message' => 'Review updated.', 'data' => $review->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review)
    {
        abort_unless($review->user_id === request()->user()->id || request()->user()->hasRole('super-admin'), 403);

        $review->delete();

        return response()->json(['status' => true, 'message' => 'Review deleted.']);
    }
}
