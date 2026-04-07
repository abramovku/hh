<?php

namespace App\Services\Telegram;

use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('telegram', function () {
            return new Telegram(config('services.telegram', []));
        });
    }
}
