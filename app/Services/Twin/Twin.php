<?php

namespace App\Services\Twin;

use Illuminate\Support\Facades\Log;

class Twin
{
    private $config;
    private $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new TwinClient($config);
    }

    public function sendMessage()
    {
        $this->client->post('message', []);
    }
}
