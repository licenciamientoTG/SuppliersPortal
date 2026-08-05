<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSessionActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserSessionMonitorController extends Controller
{
    public function index(): View
    {
        $activeUserIds = DB::table(config('session.table'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->getTimestamp())
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $users = User::query()
            ->with('roles')
            ->withMax('sessionActivities', 'started_at')
            ->withMax('sessionActivities', 'ended_at')
            ->whereNotNull('last_login')
            ->latest('last_login')
            ->paginate(25)
            ->through(function (User $user) use ($activeUserIds): User {
                $user->session_status = $activeUserIds->contains($user->id) ? 'active' : 'closed';
                $user->last_session_started_at = $user->session_activities_max_started_at
                    ? Carbon::parse($user->session_activities_max_started_at)
                    : $user->last_login;
                $user->last_session_ended_at = $user->session_activities_max_ended_at
                    ? Carbon::parse($user->session_activities_max_ended_at)
                    : null;

                return $user;
            });

        $recentSessions = UserSessionActivity::query()
            ->with(['user:id,name,email'])
            ->latest('started_at')
            ->limit(8)
            ->get();

        return view('admin.user-sessions.index', [
            'users' => $users,
            'recentSessions' => $recentSessions,
            'activeCount' => $activeUserIds->count(),
            'closedCount' => $users->total() - $activeUserIds->count(),
        ]);
    }
}
