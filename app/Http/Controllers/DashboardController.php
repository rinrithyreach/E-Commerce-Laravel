<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function admin(Request $request)
    {
        $from = $request->date('from', now()->subDays(30));
        $to = $request->date('to', now());

        return response()->json([
            'status' => true,
            'data' => [
                'orders' => Order::whereBetween('created_at', [$from, $to])->count(),
                'revenue' => (float) Order::where('payment_status', 'paid')->whereBetween('created_at', [$from, $to])->sum('total_amount'),
                'vendors' => Vendor::count(),
                'products' => Product::count(),
                'average_rating' => round((float) Review::avg('rating'), 2),
                'daily_sales' => Order::selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->orderBy('date')
                    ->get(),
            ],
        ]);
    }

    public function vendor(Request $request)
    {
        $vendorId = $request->user()->vendor?->id;
        abort_unless($vendorId || $request->user()->hasRole('super-admin'), 403);

        $orders = Order::whereHas('items', fn ($query) => $query->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId)));
        $products = Product::whereHas('store', fn ($query) => $query->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId)));

        return response()->json([
            'status' => true,
            'data' => [
                'orders' => (clone $orders)->count(),
                'revenue' => (float) (clone $orders)->where('payment_status', 'paid')->sum('total_amount'),
                'products' => $products->count(),
                'published_products' => (clone $products)->where('is_published', true)->count(),
                'low_stock_variants' => DB::table('product_variants')
                    ->join('products', 'product_variants.product_id', '=', 'products.id')
                    ->join('stores', 'products.store_id', '=', 'stores.id')
                    ->when($vendorId, fn ($query) => $query->where('stores.vendor_id', $vendorId))
                    ->where('product_variants.stock', '<=', 5)
                    ->count(),
            ],
        ]);
    }

    public function customer(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => [
                'orders' => $request->user()->orders()->count(),
                'pending_orders' => $request->user()->orders()->whereIn('status', ['pending', 'paid', 'processing'])->count(),
                'wishlist_items' => $request->user()->wishlistItems()->count(),
                'addresses' => $request->user()->addresses()->count(),
            ],
        ]);
    }
}
