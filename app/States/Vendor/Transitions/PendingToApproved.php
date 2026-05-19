<?php

namespace App\States\Vendor\Transitions;

use App\Models\Vendor;
use App\Models\Store;
use App\States\Vendor\Approved;
use Spatie\ModelStates\Transition;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class PendingToApproved extends Transition
{
    public function __construct(protected Vendor $vendor) {}

    public function handle(): Vendor
    {
        // 1. Update the status
        $this->vendor->status = new Approved($this->vendor);

        // 2. 💡 FIXED: Use updateOrCreate to gracefully bypass duplicate unique slug constraint crashes
        Store::updateOrCreate(
            ['vendor_id' => $this->vendor->id], // Lookup criteria
            [
                'name'      => $this->vendor->business_name,
                'slug'      => Str::slug($this->vendor->business_name),
                'is_active' => true,
            ]
        );

        // 3. Fetch the exact 'vendor' role model bound to the sanctum guard
        $vendorRole = Role::where('name', 'vendor')
            ->where('guard_name', 'sanctum')
            ->first();

        if ($vendorRole) {
            $this->vendor->user->assignRole($vendorRole);
        } else {
            $this->vendor->user->assignRole('vendor');
        }

        $this->vendor->save();

        return $this->vendor;
    }
}
