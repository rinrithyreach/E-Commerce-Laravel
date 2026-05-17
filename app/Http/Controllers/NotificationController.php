<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $notifications = DB::table('notifications')
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['status' => true, 'data' => $notifications]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $notification)
    {
        $row = $this->ownedNotification($notification);

        return response()->json(['status' => true, 'data' => $row]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $notification)
    {
        $this->ownedNotification($notification);

        DB::table('notifications')->where('id', $notification)->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['status' => true, 'message' => 'Notification marked as read.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $notification)
    {
        $this->ownedNotification($notification);

        DB::table('notifications')->where('id', $notification)->delete();

        return response()->json(['status' => true, 'message' => 'Notification deleted.']);
    }

    public function markAllRead(Request $request)
    {
        DB::table('notifications')
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['status' => true, 'message' => 'All notifications marked as read.']);
    }

    private function ownedNotification(string $id)
    {
        $row = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', request()->user()->id)
            ->first();

        abort_unless($row, 404);

        return $row;
    }
}
