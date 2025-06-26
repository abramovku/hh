<?php

namespace App\Services\Estaff;

use Illuminate\Support\ServiceProvider;

class TwinServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('twin', function () {
            return new Twin(config('services.twin', []));
        });
    }
}
