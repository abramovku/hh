<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ResponsesController extends Controller
{
    public function index(Request $request): View
    {
        $sort = $request->get('sort') === 'asc' ? 'asc' : 'desc';
        $sortBy = $request->get('sort_by') === 'phone' ? 'phone' : 'created_at';

        $query = Response::with('contactEvents')->orderBy($sortBy, $sort);

        if ($sortBy === 'phone') {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        if ($request->filled('vacancy')) {
            $query->where('vacancy_id', $request->vacancy);
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%'.preg_replace('/\D+/', '', $request->phone).'%');
        }

        if ($request->boolean('called')) {
            $query->whereHas('contactEvents', fn ($q) => $q->where('type', 'call'));
        }

        if ($request->boolean('sms')) {
            $query->whereHas('contactEvents', fn ($q) => $q->where('type', 'sms'));
        }

        if ($request->boolean('whatsapp')) {
            $query->whereHas('contactEvents', fn ($q) => $q->where('type', 'whatsapp'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $responses = $query->paginate(50)->withQueryString();

        return view('admin.responses.index', compact('responses', 'sort', 'sortBy'));
    }
}
