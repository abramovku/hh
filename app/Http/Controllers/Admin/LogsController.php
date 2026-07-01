<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function index(Request $request): View
    {
        $query = LogEntry::query()->latest('id');

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('level')) {
            $query->where('level_name', $request->level);
        }

        if ($request->filled('message')) {
            $query->where('message', 'like', '%'.$request->message.'%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $channels = LogEntry::query()->distinct()->orderBy('channel')->pluck('channel');
        $levels = LogEntry::query()->distinct()->orderBy('level_name')->pluck('level_name');

        return view('admin.logs.index', compact('logs', 'channels', 'levels'));
    }
}
