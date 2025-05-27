<?php

namespace App\Services\Estaff;

use Illuminate\Support\ServiceProvider;

class EstaffServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('estaff', function () {
            return new Estaff(config('services.estaff', []));
        });
    }
}
