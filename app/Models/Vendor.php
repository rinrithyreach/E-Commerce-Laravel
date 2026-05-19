<?php

namespace App\Models;

use App\States\Vendor\VendorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vendor extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\VendorFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'business_name',
        'registration_number',
        'status',
        'commission_rate',
        'support_email',
        'support_phone',
        'aba_merchant_id',
        'aba_api_key',
        'bakong_account_id',
        'bakong_merchant_name',
        'wing_account_number',
        'preferred_payout_method',
        'approved_at'

    ]; 

    #[Override]
    protected function casts()
    {
        return [
            'status' => VendorStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function user(){
        return $this->belongsTo(User::class);
    }


}
