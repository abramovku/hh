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
        Log::channel('twin')->info("twin create candidate", [$request->all()]);

        $EstaffService = app('estaff');
        $response = $EstaffService->addResponse($request->all());

        Log::channel('twin')->info("twin create candidate response", [$response]);
        return response()->json($response, 200);
    }

    public function update(UpdateCandidate $request)
    {
        Log::channel('twin')->info("twin change candidate", [$request->all()]);

        $EstaffService = app('estaff');
        $response = $EstaffService->changeCandidate($request->all());

        Log::channel('twin')->info("twin change candidate response", [$response]);
        return response()->json($response, 200);
    }

    public function state(SetStateCandidate $request)
    {
        Log::channel('twin')->info("twin set state candidate", [$request->all()]);

        $EstaffService = app('estaff');
        $response = $EstaffService->setStateCandidate($request->all());

        Log::channel('twin')->info("twin set state candidate response", [$response]);
        return response()->json($response, 200);
    }
}
