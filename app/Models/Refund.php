<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'payment_id', 'amount', 'reason', 'status', 'external_refund_id', 'raw_response'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'raw_response' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
