<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Notifications\VendorApplicationApproved;
use App\Notifications\VendorApplicationRejected;
use App\States\Vendor\Approved;
use App\States\Vendor\Rejected;
use Exception;

class AdminVendorController extends Controller
{


    /**
     * Display a paginated list of all vendor applications.
     * Accessible at: GET /api/admin/vendors
     */


    public function index(){
        try{

            $vendors = Vendor::with('media')->latest()->paginate(10);
            return response()->json([
                'status' => true,
                'vendors' => $vendors
            ]);

        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * Show the detailed profile and files of a single vendor application.
     * Accessible at: GET /api/admin/vendors/{vendor}
     */
    public function show(Vendor $vendor)
    {
        try {
            // Load the media files attached to this specific vendor application row
            $vendor->load('media');

            // Format image URLs explicitly so your Flutter/Vue frontend can display them easily
            $formattedVendor = $vendor->toArray();
            $formattedVendor['logo_url'] = $vendor->getFirstMediaUrl('logos');
            $formattedVendor['business_license_url'] = $vendor->getFirstMediaUrl('legal_documents');

            return response()->json([
                'status' => true,
                'vendor' => $formattedVendor
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Application profile not found.'], 404);
        }
    }

    /**
     * Approve vendor application
     */
    public function approve(Vendor $vendor)
    {
        try {

            if (!$vendor->status->canTransitionTo(Approved::class)) {
                return response()->json(['error' => 'This application is not pending or cannot be approved.'], 422);
            }


            $vendor->status->transitionTo(Approved::class);

            //LIVE REAL-TIME PUSH: Notify the customer who applied

            if($vendor->user){
                $vendor->user->notify(new VendorApplicationApproved($vendor));
            }

            return response()->json([
                'status' => true,
                'message' => "Vendor '{$vendor->business_name}' approved successfully. Store is now active!"
            ]);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject vendor application
     */
    public function reject(Vendor $vendor)
    {
        try {
            if (!$vendor->status->canTransitionTo(Rejected::class)) {
                return response()->json(['error' => 'This application cannot be rejected.'], 422);
            }


            $vendor->status->transitionTo(Rejected::class);

            if ($vendor->user) {
                $vendor->user->notify(new VendorApplicationRejected($vendor));
            }

            return response()->json([
                'status' => true,
                'message' => "Vendor application for '{$vendor->business_name}' has been rejected."
            ]);

        } catch (Exception $e) {
             return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
