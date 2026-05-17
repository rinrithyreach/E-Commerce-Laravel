<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'usage_limit',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < (float) $this->min_order_amount || $this->expires_at->isPast()) {
            return 0;
        }

        if ($this->usage_limit !== null && $this->usages()->count() >= $this->usage_limit) {
            return 0;
        }

        $discount = $this->type === 'percent'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $subtotal), 2);
    }
}
