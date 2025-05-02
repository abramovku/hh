<?php

namespace App\Services\HH;

use Illuminate\Support\ServiceProvider;

class HHServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('hh', function () {
            return new HH(config('services.hh', []));
        });
    }
}
