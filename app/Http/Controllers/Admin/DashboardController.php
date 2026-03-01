<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactEvent;
use App\Models\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total' => Response::count(),
            'today' => Response::whereDate('created_at', today())->count(),
            'week' => Response::where('created_at', '>=', now()->startOfWeek())->count(),
            'month' => Response::where('created_at', '>=', now()->startOfMonth())->count(),
            'called' => ContactEvent::where('type', 'call')->count(),
            'sms' => ContactEvent::where('type', 'sms')->count(),
            'whatsapp' => ContactEvent::where('type', 'whatsapp')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
