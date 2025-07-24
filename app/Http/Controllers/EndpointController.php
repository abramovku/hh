<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCandidate;
use App\Http\Requests\EventCandidate;
use App\Http\Requests\FindCandidate;
use App\Http\Requests\FindVacancy;
use App\Http\Requests\GetCandidate;
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

    public function get(GetCandidate $request)
    {
        Log::channel('app')->info("twin get candidate", [$request->all()]);
        try {
            $EstaffService = app('estaff');
            $response = $EstaffService->getCandidateFull($request->all());
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
        Log::channel('app')->info("twin get candidate response", [$response]);
        return response()->json($response, 200);
    }

    public function find(FindCandidate $request)
    {
        Log::channel('app')->info("twin find candidate", [$request->all()]);
        try {
            $EstaffService = app('estaff');
            $response = $EstaffService->findCandidateFull($request->all());
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
        Log::channel('app')->info("twin find candidate response", [$response]);
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

    public function event(EventCandidate $request)
    {
        Log::channel('app')->info("twin add event candidate", [$request->all()]);
        try {
            $EstaffService = app('estaff');
            $response = $EstaffService->eventCandidate($request->all());
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
        Log::channel('app')->info("twin add event candidate response", [$response]);
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

    public function findVacancy(FindVacancy $request)
    {
        Log::channel('app')->info("twin find vacancy", [$request->all()]);
        try {
            $EstaffService = app('estaff');
            $response = $EstaffService->findVacancyFull($request->all());
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
        Log::channel('app')->info("twin find vacancy response", [$response]);
        return response()->json($response, 200);
    }
}
