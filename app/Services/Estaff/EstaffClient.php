<?php

namespace App\Services\Estaff;

use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class EstaffClient
{
    private $client;
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $stack = HandlerStack::create(new StreamHandler());
        $this->client = app('GuzzleClient')([
            'handler' => $stack,
            'http_errors'  => false,
            'base_uri' => $config['url'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $config['token']
            ],
        ]);
    }

    public function post($link, $data)
    {
        return $this->request('POST', $link, ['json' => $data]);
    }

    private function request(string $type, string $requestUrl, array $data = [])
    {
        try {
            $response = $this->client->request($type, $requestUrl, $data);
            $response = json_decode($response->getBody(), true);
            return $response ?? [];
        } catch (RequestException $e) {
            $code = $e->getCode();
            $response = json_decode($e->getResponse()->getBody(), true);
            Log::channel('estaff')->error('Estaff Service http request failed',
                ['requestUrl' => $requestUrl, 'code' => $code, 'response' => $response]);
            throw $e;
        }
    }
}
