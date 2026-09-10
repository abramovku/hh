<?php

namespace App\Services\Location;

use Illuminate\Support\ServiceProvider;

class LocationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('location', function () {
            return new Location(config('services.location', []));
        });
    }
}
