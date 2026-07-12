<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\OpenAI\OpenAI;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend(PrismManager::class, function (PrismManager $manager, $app) {
            $manager->extend('opencode', function ($app, $config) {
                if (blank($config['api_key'] ?? '')) {
                    throw PrismException::providerResponseError(
                        'OpenCode Zen API key is not set. Set OPENCODE_API_KEY in your .env file.'
                    );
                }

                return new OpenAI(
                    apiKey: $config['api_key'],
                    url: $config['url'],
                    organization: null,
                    project: null,
                );
            });

            return $manager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mcp', function ($request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
