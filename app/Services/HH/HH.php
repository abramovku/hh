<?php

namespace App\Services\HH;

class HH
{
    private  $HHClient;

    public function __construct($config)
    {
        $this->HHClient = new HHClient($config);
    }

    public function baseInstall(string $code)
    {
        if(!empty($code)) $this->HHClient->auth($code);
    }
}
