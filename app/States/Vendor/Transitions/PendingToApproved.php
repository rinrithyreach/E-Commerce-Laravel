<?php
namespace App\States\Vendor\Transitions;

use App\Models\Vendor;
use App\Models\Store;
use App\States\Vendor\Approved;
use Spatie\ModelStates\Transition;
use Illuminate\Support\Str;

class PendingToApproved extends Transition
{
    public function __construct(protected Vendor $vendor) {}

    public function handle(): Vendor
    {
        // 1. Update the status
        $this->vendor->status = new Approved($this->vendor);

        // 2. Automatically create the Store record
        Store::create([
            'vendor_id' => $this->vendor->id,
            'name' => $this->vendor->business_name,
            'slug' => Str::slug($this->vendor->business_name),
            'is_active' => true,
        ]);

        // 3. Assign the 'vendor' role to the User
        $this->vendor->user->assignRole('vendor');

        $this->vendor->save();

        return $this->vendor;
    }
}
