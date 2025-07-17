<?php

namespace App\Services\Twin;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TwinClient
{
    private $client;
    private $config;
    private $token;
    private $refreshToken;
    private $tries;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->tries = 0;
        $settings = $this->getConfig();

        if (!empty($settings['token'])) {
            $this->token = $settings['token'];
        }

        if (!empty($settings['refreshToken'])) {
            $this->refreshToken = $settings['refreshToken'];
        }

        $this->client = app('GuzzleClient')([
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);
    }

    public function getConfig(): array
    {
        $authSettings = DB::table('settings')
            ->where('key', 'twin_credentials')->first();
        if (!empty($authSettings)) {
            $return = json_decode($authSettings->value, true);
            if (!empty($return['token']) && !empty($return['refreshToken'])) {
                return $return;
            }
        }
        return [];
    }

    public function setConfig($data): void
    {
        if (!empty($data['token'])) {
            $settingTable = DB::table('settings');
            $setting = $settingTable->where('key', 'twin_credentials')->first();
            if (empty($setting)) {
                $settingTable->insert(['key' => 'twin_credentials', 'value' => json_encode($data)]);
            } else {
                $newData = array_merge(json_decode($setting->value, true), $data);
                $settingTable->where('key', 'twin_credentials')->update(['value' => json_encode($newData)]);
            }
            $this->token = $data['token'];
            $this->refreshToken = $data['refreshToken'];
            return;
        }
        throw new \Exception('Set twin config - wrong params');
    }

    /**
     * @param $token
     * @return void
     * @throws \Exception
     */
    public function auth(string $token = ''): void
    {
        if (!empty($token)) {
            $data = [
                'refreshToken' => $token,
            ];
            $newAuthData = $this->post('auth/refresh', $data, true);
        } else {
            $data = [
                'email' => $this->config['auth_email'],
                'password' => $this->config['auth_password'],
            ];
            $newAuthData = $this->post('auth/login', $data, true);
        }

        $this->setConfig($newAuthData);
    }

    public function post(string $requestUrl, array $data, bool $auth = false): array
    {

        if ($auth === true) {
            $requestUrl = $this->config['auth_url'] . $requestUrl;
        }
        return $this->request('POST', $requestUrl, ['json' => $data], $auth);
    }

    private function request(string $type, string $requestUrl, array $data = [], bool $auth = false)
    {
        if ($this->tries >= 2) {
            throw new \Exception('twin-service too many tries');
        }
        if ($auth === false) {
            if (empty($this->token)) {
                $this->auth();
                ++$this->tries;
                return $this->request($type, $requestUrl, $data);
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
                Log::channel('twin')->error('Twin Service http request failed',
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
