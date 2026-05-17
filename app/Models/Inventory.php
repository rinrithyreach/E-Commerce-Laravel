<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'quantity_change',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
        ];
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
