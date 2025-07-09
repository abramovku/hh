<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCandidate;
use App\Http\Requests\SetStateCandidate;
use App\Http\Requests\UpdateCandidate;
use Illuminate\Support\Facades\Log;

class EndpointController extends Controller
{
    public function create(AddCandidate $request)
    {
        Log::channel('app')->info("twin create candidate", [$request->all()]);
        try {
            $EstaffService = app('estaff');
            $response = $EstaffService->addResponse($request->all());
        } catch (\Exception $e) {
            Log::channel('app')->error(
                'Estaff service error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
            return response()->json([
                'success' => false,
                'message' => 'Estaff return error.',
                'error' => $e->getMessage(),
                'data' => [],
            ]);
        }
        Log::channel('app')->info("twin create candidate response", [$response]);
        return response()->json($response, 200);
    }

    public function update(UpdateCandidate $request)
    {
        Log::channel('app')->info("twin change candidate", [$request->all()]);
        try {
            $EstaffService = app('estaff');
            $response = $EstaffService->changeCandidate($request->all());
        } catch (\Exception $e) {
            Log::channel('app')->error(
            'Estaff service error',
            [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            ]
            );
            return response()->json([
            'success' => false,
            'message' => 'Estaff return error.',
            'error' => $e->getMessage(),
            'data' => [],
            ]);
        }
        Log::channel('app')->info("twin change candidate response", [$response]);
        return response()->json($response, 200);
    }

    public function state(SetStateCandidate $request)
    {
        Log::channel('app')->info("twin set state candidate", [$request->all()]);
        try {
            $EstaffService = app('estaff');
            $response = $EstaffService->setStateCandidate($request->all());
        } catch (\Exception $e) {
            Log::channel('app')->error(
                'Estaff service error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
            return response()->json([
                'success' => false,
                'message' => 'Estaff return error.',
                'error' => $e->getMessage(),
                'data' => [],
            ]);
        }


        Log::channel('app')->info("twin set state candidate response", [$response]);
        return response()->json($response, 200);
    }
}
