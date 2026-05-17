<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'attributes',
        'price_modifier',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price_modifier' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(Inventory::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
