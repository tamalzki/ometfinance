<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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

        // Polyfill the @selected/@checked/@disabled/@required/@readonly Blade
        // directives. They only ship with Laravel 9.21+, but this app runs
        // ^8.75 — editor formatters keep "modernizing" attribute ternaries
        // back into these directives, which then render as broken literal
        // HTML and break Alpine.js parsing. Defining them ourselves makes
        // either form compile correctly, so the formatter can't break the page.
        foreach (['checked', 'selected', 'disabled', 'required', 'readonly'] as $attribute) {
            Blade::directive($attribute, function ($expression) use ($attribute) {
                return "<?php if ({$expression}): echo ' {$attribute}'; endif; ?>";
            });
        }
    }
}
