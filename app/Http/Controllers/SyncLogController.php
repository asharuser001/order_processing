<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SyncLogController
 *
 * Displays the history of order sync operations for the authenticated shop.
 */
class SyncLogController extends Controller
{
    /**
     * Paginated sync log list.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $logs = SyncLog::where('user_id', $user->id)
            ->orderByDesc('started_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SyncLogs/Index', [
            'logs' => $logs,
        ]);
    }
}
