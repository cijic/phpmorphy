<?php

namespace cijic\phpMorphy;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class MorphyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        App::bind('morphy', function () {
            return new Morphy();
        });
    }
}
