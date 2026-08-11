<?php

namespace App\Traits;

trait GeneratesRid
{
    /**
     * Short correlation id shared by the paired `send`/`get` log entries of one external call.
     */
    protected function newRid(): string
    {
        return bin2hex(random_bytes(4));
    }
}
