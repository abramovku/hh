<?php

namespace Tests\Unit;

use App\Services\Location\Location;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class LocationServiceTest extends TestCase
{
    private const URL = 'https://location.test/gloria_jeans_mall';

    /** @var array<int, array{request: Request}> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Channel writes to DB in production; keep tests DB-free.
        config(['logging.channels.location' => ['driver' => 'null']]);
    }

    /**
     * Replace the shared GuzzleClient factory with one backed by a MockHandler queue.
     *
     * @param  array<int, Response|\Throwable>  $queue
     */
    private function fakeGuzzle(array $queue): void
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        $this->app->bind('GuzzleClient', function () use ($stack) {
            return function ($config = []) use ($stack) {
                return new Client(array_merge($config, ['handler' => $stack]));
            };
        });
    }

    private function service(?string $url = self::URL, int $retries = 1): Location
    {
        return new Location(['url' => $url, 'timeout' => 5, 'retries' => $retries, 'verify_ssl' => false]);
    }

    private function jsonResponse(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private function connectException(): ConnectException
    {
        return new ConnectException('cURL error 28: timeout', new Request('GET', self::URL.'/region'));
    }

    public function test_returns_location_id_and_normalizes_phone(): void
    {
        $this->fakeGuzzle([$this->jsonResponse([
            'number' => '79001112233',
            'region' => 'Волгоградская обл.',
            'operator' => 'ПАО "ВЫМПЕЛКОМ"',
            'location_id' => 'Yug',
        ])]);

        $this->assertSame('Yug', $this->service()->locationId('+7 (900) 111-22-33'));

        $this->assertCount(1, $this->history);
        $this->assertSame(self::URL.'/region?number=79001112233', (string) $this->history[0]['request']->getUri());
        $this->assertSame('GET', $this->history[0]['request']->getMethod());
    }

    public function test_leading_eight_is_replaced_with_seven(): void
    {
        $this->fakeGuzzle([$this->jsonResponse(['location_id' => 'Centr'])]);

        $this->service()->locationId('8 900 111-22-33, 8 900 000-00-00');

        $this->assertSame('number=79001112233', $this->history[0]['request']->getUri()->getQuery());
    }

    public function test_returns_null_when_location_id_empty(): void
    {
        $this->fakeGuzzle([$this->jsonResponse(['number' => '79001112233', 'location_id' => null])]);

        $this->assertNull($this->service()->locationId('79001112233'));
    }

    public function test_404_region_not_found_is_not_retried(): void
    {
        $this->fakeGuzzle([$this->jsonResponse(['detail' => 'Region not found for the provided number'], 404)]);

        $this->assertNull($this->service()->locationId('71111111111'));
        $this->assertCount(1, $this->history);
    }

    public function test_5xx_is_retried_once_then_null(): void
    {
        $this->fakeGuzzle([new Response(500, [], 'error'), new Response(502, [], 'error')]);

        $this->assertNull($this->service()->locationId('79001112233'));
        $this->assertCount(2, $this->history);
    }

    public function test_5xx_then_success_returns_location_id(): void
    {
        $this->fakeGuzzle([new Response(500, [], 'error'), $this->jsonResponse(['location_id' => 'Yug'])]);

        $this->assertSame('Yug', $this->service()->locationId('79001112233'));
        $this->assertCount(2, $this->history);
    }

    public function test_timeout_is_retried_then_null(): void
    {
        $this->fakeGuzzle([$this->connectException(), $this->connectException()]);

        $this->assertNull($this->service()->locationId('79001112233'));
        $this->assertSame([], $this->service()->lookup('79001112233'));
    }

    public function test_retries_count_is_configurable(): void
    {
        $this->fakeGuzzle([$this->connectException(), $this->connectException(), $this->jsonResponse(['location_id' => 'Yug'])]);

        $this->assertSame('Yug', $this->service(self::URL, 2)->locationId('79001112233'));
        $this->assertCount(3, $this->history);
    }

    public function test_returns_null_on_invalid_json(): void
    {
        $this->fakeGuzzle([new Response(200, [], 'not json')]);

        $this->assertNull($this->service()->locationId('79001112233'));
    }

    public function test_invalid_phone_skips_request(): void
    {
        $this->fakeGuzzle([]);

        $this->assertNull($this->service()->locationId('12345'));
        $this->assertCount(0, $this->history);
    }

    public function test_not_configured_skips_request(): void
    {
        $this->fakeGuzzle([]);

        $this->assertNull($this->service(null)->locationId('79001112233'));
        $this->assertCount(0, $this->history);
    }
}
