<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function estaff(): JsonResponse
    {
        try {
            $result = app('estaff')->ping();
        } catch (\Exception $e) {
            Log::channel('estaff')->error('health check failed', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        Log::channel('estaff')->info('health check', $result);

        return response()->json($result);
    }
}
