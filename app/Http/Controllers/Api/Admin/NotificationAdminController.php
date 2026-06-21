<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationAdminController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'target'  => 'required|in:all,vendors,drivers,user',
            'user_id' => 'required_if:target,user|nullable|exists:users,id',
            'title'   => 'required|string|max:100',
            'body'    => 'required|string|max:500',
        ]);

        $users = match($request->target) {
            'all'     => User::whereIn('role', ['vendor', 'driver'])->get(),
            'vendors' => User::where('role', 'vendor')->get(),
            'drivers' => User::where('role', 'driver')
                             ->where('approval_status', 'approved')->get(),
            'user'    => User::where('id', $request->user_id)->get(),
        };

        $notification = new NotificationService();
        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $notification->sendToUser(
                    user:  $user,
                    title: $request->title,
                    body:  $request->body,
                    data:  ['type' => 'admin_broadcast'],
                );
                $sent++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return response()->json([
            'message' => "تم الإرسال بنجاح",
            'sent'    => $sent,
            'failed'  => $failed,
            'total'   => $users->count(),
        ]);
    }

    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');
        $users = User::whereIn('role', ['vendor', 'driver'])
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                  ->orWhere('phone', 'like', "%$query%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone', 'role']);

        return response()->json(['data' => $users]);
    }
}