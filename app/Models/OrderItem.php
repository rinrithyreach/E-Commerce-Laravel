<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_variant_id',
        'vendor_id',
        'quantity',
        'price_at_purchase',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_at_purchase' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
