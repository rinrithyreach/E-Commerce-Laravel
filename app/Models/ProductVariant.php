<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
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
            'attributes' => 'array', // 💡 Automatically converts JSON strings to PHP arrays
            'price_modifier' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship listing down to your inventory ledger histories.
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }
}
