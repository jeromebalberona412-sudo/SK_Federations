<?php

namespace App\Modules\Communications\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PresenceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        $validated = $request->validate([
            'online' => ['sometimes', 'boolean'],
        ]);

        $updates = [];
        if (Schema::hasColumn('users', 'last_seen')) {
            $updates['last_seen'] = now();
        }
        if (Schema::hasColumn('users', 'online_status') && array_key_exists('online', $validated)) {
            $updates['online_status'] = $validated['online'] ? 'online' : 'offline';
        } elseif (Schema::hasColumn('users', 'online_status')) {
            $updates['online_status'] = 'online';
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        return response()->json(['ok' => true]);
    }
}
