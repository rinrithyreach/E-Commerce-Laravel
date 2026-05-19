<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorApplicationRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\NewVendorApplication;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class VendorApplicationController extends Controller
{
    public function apply(VendorApplicationRequest $request)
    {
        try {
            // Execute safe database operations inside the automated transaction wrapper
            $vendor = DB::transaction(function () use ($request) {

                // 1. Create the base vendor application record
                $vendor = Vendor::create([
                    'user_id'                 => Auth::id(),
                    'business_name'           => $request->business_name,
                    'registration_number'     => $request->registration_number,
                    'support_email'           => $request->support_email,
                    'support_phone'           => $request->support_phone,
                    'aba_merchant_id'         => $request->aba_merchant_id,
                    'aba_api_key'             => $request->aba_api_key,
                    'bakong_account_id'       => $request->bakong_account_id,
                    'bakong_merchant_name'    => $request->bakong_merchant_name,
                    'wing_account_number'     => $request->wing_account_number,
                    'preferred_payout_method' => $request->preferred_payout_method,
                    // 'status' will fallback to 'pending' from your migration automatically
                ]);

                // 2. FIXED: Process file uploads if the request HAS the file (Removed '!')
                if ($request->hasFile('logo')) {
                    $vendor->addMediaFromRequest('logo')->toMediaCollection('logos');
                }

                if ($request->hasFile('business_license')) {
                    $vendor->addMediaFromRequest('business_license')->toMediaCollection('legal_documents');
                }

                // 3. Dispatch Notification system alerts to designated admin monitors
                $admins = User::role('super-admin', 'sanctum')->get();
                Notification::send($admins, new NewVendorApplication($vendor));

                // Return the completely hydrated vendor object out of the transaction block
                return $vendor;
            });


            return response()->json([
                'status'  => true,
                'message' => 'Your application has been submitted and is under review.',
                'vendor'  => $vendor->load('media')
            ], 201);

        } catch (Exception $e) {
            // If anything inside the closure threw an error, it is rolled back completely.
            return response()->json([
                'status'  => false,
                'message' => 'Application submission failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
