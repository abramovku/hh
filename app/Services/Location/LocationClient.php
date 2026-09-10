<?php

namespace App\Services\Location;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class LocationClient
{
    private $client;

    private $config;

    /** Total attempts: 1 + configured retries. */
    private int $tries;

    /**
     * Per-request Guzzle options. The GuzzleClient factory (AppServiceProvider)
     * overwrites constructor 'timeout' / 'connect_timeout' with its 60s defaults,
     * so the spec'd 5s timeout has to be applied on the request level.
     */
    private array $options;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->tries = max(1, (int) ($config['retries'] ?? 1) + 1);

        $timeout = (int) ($config['timeout'] ?? 5);
        $this->options = [
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
            'verify' => (bool) ($config['verify_ssl'] ?? false),
        ];

        $this->client = app('GuzzleClient')([
            'base_uri' => rtrim((string) ($config['url'] ?? ''), '/').'/',
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function get(string $link, array $query = []): array
    {
        return $this->request('GET', $link, ['query' => $query]);
    }

    private function request(string $type, string $requestUrl, array $data = []): array
    {
        $data += $this->options;

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $this->client->request($type, $requestUrl, $data);
                $response = json_decode($response->getBody(), true);

                if (! is_array($response)) {
                    throw new \Exception('Location Service invalid JSON response');
                }

                return $response;
            } catch (ConnectException $e) {
                // Timeout / DNS / refused connection — retryable.
                Log::channel('location')->error(
                    'Location Service http request failed',
                    ['requestUrl' => $requestUrl, 'attempt' => $attempt, 'message' => $e->getMessage()]
                );
                if ($attempt >= $this->tries) {
                    throw $e;
                }
            } catch (RequestException $e) {
                $code = $e->getCode();
                $response = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : null;
                Log::channel('location')->error(
                    'Location Service http request failed',
                    ['requestUrl' => $requestUrl, 'attempt' => $attempt, 'code' => $code, 'response' => $response]
                );
                // 4xx (404 region not found, 400 bad number) is final; retry only 5xx.
                if ($code < 500 || $attempt >= $this->tries) {
                    throw $e;
                }
            }
        }
    }
}
