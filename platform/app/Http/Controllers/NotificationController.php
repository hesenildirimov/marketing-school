<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

/** Platforma daxili bildirişlər (e-maildən ayrı kanal). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = AppNotification::where('user_id', $request->user()->id)->where('channel', 'in_app')->where('status', 'sent')
            ->where('scheduled_for', '<=', now())->orderByDesc('scheduled_for')->paginate(30);
        AppNotification::where('user_id', $request->user()->id)->where('channel', 'in_app')->whereNull('read_at')->where('scheduled_for', '<=', now())->update(['read_at' => now()]);

        return view('notifications.index', compact('items'));
    }

    public static function unread(int $userId): int
    {
        return AppNotification::where('user_id', $userId)->where('channel', 'in_app')->where('status', 'sent')->whereNull('read_at')->where('scheduled_for', '<=', now())->count();
    }
}
