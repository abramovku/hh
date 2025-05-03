<?php

namespace App\Services\HH;

use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class HHClient
{
    private $client;
    private string $token;
    private int $tries;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $apiUrl;

    public function __construct(array $config)
    {
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->redirectUri = $config['redirect_uri'];
        $this->apiUrl = $config['api_url'];
        $this->tries = 0;

        $settings = $this->getConfig();
        if (!empty($settings['access_token'])) {
            $this->token = $settings['access_token'];
        }

        $clientParams = [
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ],
        ];

        $this->client = app('GuzzleClient')($clientParams);
    }

    public function getConfig(): array
    {
        $authSettings = DB::table('settings')
            ->where('key', 'hh_credentials')->first();
        if (!empty($authSettings)) {
            $return = json_decode($authSettings->value, true);
            if (!empty($return['access_token']) && !empty($return['refresh_token'])) {
                return $return;
            }
        }
        return [];
    }

    public function setConfig($data): void
    {
        if (!empty($data['access_token'])) {
            $settingTable = DB::table('settings');
            $setting = $settingTable->where('key', 'hh_credentials')->first();
            if (empty($setting)) {
                $settingTable->insert(['key' => 'hh_credentials', 'value' => json_encode($data)]);
            } else {
                $newData = array_merge(json_decode($setting->value, true), $data);
                $settingTable->where('key', 'hh_credentials')->update(['value' => json_encode($newData)]);
            }
            $this->token = $data['access_token'];
            return;
        }
        throw new \Exception('Set hh config - wrong params');
    }

    public function auth($code = false): void
    {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];
        if ($code) {
            $data['redirect_uri'] = $this->redirectUri;
            $data['grant_type'] = 'authorization_code';
            $data['code'] = $code;
        } else {
            $settings = $this->getConfig();
            $data['grant_type'] = 'refresh_token';
            $data['refresh_token'] = $settings['refresh_token'];
        }
        $newAuthData = $this->post('/token', $data, true);
        $this->setConfig($newAuthData);
    }

    public function get(string $requestUrl): array
    {
        return $this->request('GET', $this->apiUrl . $requestUrl);
    }

    /**
     * @param string $requestUrl
     * @param array $data
     * @param $auth
     * @return array
     * @throws \Exception
     */
    public function post(string $requestUrl, array $data, bool $auth = false): array
    {
        if ($auth === false) {
            $requestUrl = $this->apiUrl . $requestUrl;
            $data = (!empty($data)) ? ['json' => $data] : [];
        } else {
            $data = ['form_params' => $data];
        }
        return $this->request('POST', $requestUrl, $data, $auth);
    }


    private function request(string $type, string $requestUrl, array $data = [], bool $auth = false)
    {
        if ($this->tries >= 2) {
            throw new \Exception('hh-service too many tries');
        }
        if ($auth === false) {
            if (empty($this->token)) {
                throw new \Exception('hh auth token not found');
            }
            $data = array_merge($data, [
                'headers' => ['Authorization' => 'Bearer ' . $this->token]
            ]);
        }
        try {
            $response = $this->client->request($type, $requestUrl, $data);
            $response = json_decode($response->getBody(), true);
            $this->tries = 0;
            return $response ?? [];
        } catch (RequestException $e) {
            $code = $e->getCode();
            $response = json_decode($e->getResponse()->getBody(), true);
            if ($code !== 401 || $this->tries > 0) {
                Log::channel('hh')->error('HH Service http request failed',
                    ['requestUrl' => $requestUrl, 'code' => $code, 'response' => $response]);
            }

            if ($code == 401  && $auth === false) {
                $this->auth();
                ++$this->tries;
                return $this->request($type, $requestUrl, $data, $auth);
            } else {
                throw $e;
            }
        }
    }
}
