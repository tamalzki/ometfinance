<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Force HTTPS for all generated URLs in non-local environments so
        // links, redirects, and form actions never downgrade to plain HTTP.
        if ($this->app->environment('production', 'staging')) {
            URL::forceScheme('https');
        }
    }
}
