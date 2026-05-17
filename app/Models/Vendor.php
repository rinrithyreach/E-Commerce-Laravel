<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    /** @use HasFactory<\Database\Factories\VendorFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'registration_number',
        'status',
        'commission_rate',
        'aba_merchant_id',
        'aba_api_key',
        'bakong_account_id',
        'bakong_merchant_name',
        'wing_account_number',
        'preferred_payout_method',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
