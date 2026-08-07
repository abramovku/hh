<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LogsController extends Controller
{
    // Slug => monolog logger name stored in the `channel` column (see `name` in config/logging.php)
    private const CHANNELS = ['app' => 'AppLog', 'hh' => 'HHLog', 'estaff' => 'EstaffLog', 'twin' => 'TwinLog'];

    private const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function index(Request $request): View
    {
        $sort = $request->get('sort') === 'asc' ? 'asc' : 'desc';

        $query = LogEntry::query()->orderBy('created_at', $sort)->orderBy('id', $sort);

        if ($request->filled('id')) {
            $query->where('id', (int) $request->id);
        }

        if ($request->filled('channel') && isset(self::CHANNELS[$request->channel])) {
            $query->where('channel', self::CHANNELS[$request->channel]);
        }

        if ($request->filled('level')) {
            $query->where('level_name', $request->level);
        }

        if ($request->filled('message') && ($fts = $this->ftsQuery($request->message)) !== '') {
            $query->whereFullText('message', $fts, ['mode' => 'boolean']);
        }

        if ($request->filled('context') && ($fts = $this->ftsQuery($request->context)) !== '') {
            $query->whereFullText('context', $fts, ['mode' => 'boolean']);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $logs = $query->simplePaginate(50)->withQueryString();

        return view('admin.logs.index', [
            'logs' => $logs,
            'channels' => array_keys(self::CHANNELS),
            'channelLabels' => array_flip(self::CHANNELS),
            'levels' => self::LEVELS,
            'sort' => $sort,
        ]);
    }

    private function ftsQuery(string $term): string
    {
        $words = preg_split('/[^\p{L}\p{N}_]+/u', $term, -1, PREG_SPLIT_NO_EMPTY);

        return collect($words)->map(fn (string $word) => '+'.$word.'*')->implode(' ');
    }
}
