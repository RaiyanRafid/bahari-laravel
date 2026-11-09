<?php

namespace Bahari\Laravel;

use Bahari\Laravel\Contracts\SessionRepository;
use Bahari\Laravel\Http\Middleware\Authenticate;
use Bahari\Sso\Client;
use Bahari\Sso\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class BahariServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bahari.php', 'bahari');

        $this->app->singleton(Client::class, fn ($app): Client => new Client(
            Config::fromArray(config('bahari')),
            new LaravelHttpClient,
            Cache::store(config('bahari.cache_store')),
            Sdk::PACKAGE.'/'.Sdk::VERSION,
            fn (string $value): string => Crypt::encryptString($value),
            fn (string $value): string => Crypt::decryptString($value),
        ));
        $this->app->bind(SessionRepository::class, fn ($app) => $app->make(config('bahari.session_repository', EloquentSessionRepository::class)));
        $this->app->singleton(BahariManager::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/bahari.php' => config_path('bahari.php')], 'bahari-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'bahari-migrations');
        $this->app['router']->aliasMiddleware('bahari.auth', Authenticate::class);
        if ($this->app->runningInConsole()) {
            $this->commands([Console\ConnectCommand::class]);
        }
        if (config('bahari.routes.enabled', true) && ! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/bahari.php');
            $this->app->booted(function (): void {
                $router = $this->app['router'];
                if (config('bahari.routes.login_alias', true) && ! $router->has('login')) {
                    $router->get('login', fn (\Illuminate\Http\Request $request) => redirect()->route(config('bahari.routes.name', 'bahari.').'signin', array_filter(['return_to' => $request->query('return_to')])))
                        ->middleware(config('bahari.routes.middleware', ['web']))->name('login');
                    $router->getRoutes()->refreshNameLookups();
                }
            });
        }

        // Tell the developers once a day when Bahari reports this package as outdated or unsupported.
        $this->app->terminating(function (): void {
            if (! $this->app->resolved(Client::class)) {
                return;
            }
            $status = $this->app->make(Client::class)->sdkStatus();
            if (in_array($status, ['outdated', 'unsupported'], true) && Cache::add('bahari-sdk-warned:'.$status, true, 86400)) {
                Log::warning('Bahari reports '.Sdk::PACKAGE.' '.Sdk::VERSION.' as '.$status.'. Run: composer update bahari/laravel bahari/sso-php');
            }
        });
    }
}
