<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Display a paginated audit log of system activities.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $module = (string) $request->query('module', 'all');
        $action = (string) $request->query('action', 'all');
        $userId = (string) $request->query('user_id', 'all');

        $logs = ActivityLog::query()
            ->with('user:id,name,username,role')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%");
                });
            })
            ->when($module !== '' && $module !== 'all', fn ($query) => $query->where('module', $module))
            ->when($action !== '' && $action !== 'all', fn ($query) => $query->where('action', $action))
            ->when($userId !== '' && $userId !== 'all', fn ($query) => $query->where('user_id', (int) $userId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $users = User::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('activity-logs/Index', [
            'logs' => $logs,
            'users' => $users,
            'filters' => [
                'search' => $search,
                'module' => $module,
                'action' => $action,
                'user_id' => $userId,
            ],
        ]);
    }
}
