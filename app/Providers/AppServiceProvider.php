<?php

namespace App\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Support\ServiceProvider;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->bind('GuzzleClient', function () {

            $stack = HandlerStack::create();

            if (config('services.guzzle.log') === true) {
                $messageFormats = [
                    'REQUEST: {method} - {uri} - HTTP/{version} - {req_headers} - {req_body}',
                    'RESPONSE: {code} - {res_body}',
                ];

                collect($messageFormats)->each(function ($messageFormat) use ($stack) {
                    // We'll use unshift instead of push, to add the middleware to the bottom of the stack, not the top
                    $stack->unshift(
                        Middleware::log(
                            with(new Logger('guzzle-log'))->pushHandler(
                                new RotatingFileHandler(storage_path('logs/guzzle-log.log'))
                            ),
                            new MessageFormatter($messageFormat)
                        )
                    );
                });
            }

            return function ($config = []) use ($stack) {
                return new Client(array_merge($config, ['handler' => $stack,
                    'timeout' => 60, 'connect_timeout' => 60
                ]));
            };
        });
    }
}
