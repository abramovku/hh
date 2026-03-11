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
        $query = Response::with(['contactEvents', 'meta' => fn ($q) => $q->where('key', 'cell')])
            ->latest();

        if ($request->filled('vacancy')) {
            $query->where('vacancy_id', $request->vacancy);
        }

        if ($request->filled('phone')) {
            $query->whereHas(
                'meta',
                fn ($q) => $q
                    ->where('key', 'cell')
                    ->where('value', 'like', '%'.$request->phone.'%')
            );
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

        $responses = $query->paginate(50)->withQueryString();

        return view('admin.responses.index', compact('responses'));
    }
}
