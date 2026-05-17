<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'coupon_id',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'shipping_fee',
        'shipping_address',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
