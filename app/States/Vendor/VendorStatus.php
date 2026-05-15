<?php

namespace App\States\Vendor;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use App\States\Vendor\Transitions\PendingToApproved;

abstract class VendorStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Approved::class, PendingToApproved::class) // Use the class here
            ->allowTransition(Pending::class, Rejected::class)
            ->allowTransition(Rejected::class, Pending::class);
    }
}
