<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCandidate;
use App\Http\Requests\EventCandidate;
use App\Http\Requests\FindCandidate;
use App\Http\Requests\FindVacancy;
use App\Http\Requests\GetCandidate;
use App\Http\Requests\GetVacancy;
use App\Http\Requests\SetStateCandidate;
use App\Http\Requests\UpdateCandidate;
use App\Services\Flow\CandidateLocationEnricher;
use App\Support\Flow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EndpointController extends Controller
{
    private function callEstaff(string $action, string $method, FormRequest|array $request): JsonResponse
    {
        $params = $request instanceof FormRequest ? $request->all() : $request;

        Log::channel('app')->info('twin '.$action, [$params]);
        try {
            $response = app('estaff')->{$method}($params);
        } catch (\Exception $e) {
            Log::channel('app')->error('Estaff service error', [
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);

            return response()->json(['success' => false, 'message' => 'Estaff return error.', 'error' => $e->getMessage(), 'data' => []]);
        }
        Log::channel('app')->info('twin '.$action.' response', [$response]);

        return response()->json($response, 200);
    }

    public function create(AddCandidate $request): JsonResponse
    {
        $params = $request->all();
        if (Flow::newFlowEnabled()) {
            $params = app(CandidateLocationEnricher::class)->enrichCreatePayload($params); // ТЗ 1
        }

        return $this->callEstaff('create candidate', 'addResponse', $params);
    }

    public function get(GetCandidate $request): JsonResponse
    {
        return $this->callEstaff('get candidate', 'getCandidateFull', $request);
    }

    public function find(FindCandidate $request): JsonResponse
    {
        return $this->callEstaff('find candidate', 'findCandidateFull', $request);
    }

    public function update(UpdateCandidate $request): JsonResponse
    {
        $params = $request->all();
        if (Flow::newFlowEnabled()) {
            $params = app(CandidateLocationEnricher::class)->enrichUpdatePayload($params); // ТЗ 1
        }

        return $this->callEstaff('change candidate', 'changeCandidate', $params);
    }

    public function event(EventCandidate $request): JsonResponse
    {
        return $this->callEstaff('add event candidate', 'eventCandidate', $request);
    }

    public function state(SetStateCandidate $request): JsonResponse
    {
        return $this->callEstaff('set state candidate', 'setStateCandidate', $request);
    }

    public function findVacancy(FindVacancy $request): JsonResponse
    {
        return $this->callEstaff('find vacancy', 'findVacancyFull', $request);
    }

    public function getVacancy(GetVacancy $request): JsonResponse
    {
        return $this->callEstaff('get vacancy', 'getVacancyFull', $request);
    }
}
