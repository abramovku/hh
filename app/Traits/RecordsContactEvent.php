<?php

namespace App\Traits;

use App\Models\ContactEvent;
use App\Models\Response;

trait RecordsContactEvent
{
    private function recordContact(int $candidateEstaff, string $type): void
    {
        $response = Response::where('candidate_estaff', $candidateEstaff)->latest()->first();
        ContactEvent::create(['response_id' => $response?->id, 'type' => $type]);
    }
}
