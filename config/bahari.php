<?php

return [
    // The permanent Bahari issuer. Admin host, endpoints and API URLs are discovered from it.
    'issuer' => env('BAHARI_ACCOUNTS_ISSUER', 'https://accounts.baharione.com'),
    'client_id' => env('BAHARI_CLIENT_ID'),
    'client_secret' => env('BAHARI_CLIENT_SECRET'),
    // This app's platform key in Bahari. Required for admin sign-in and wallet links.
    'platform' => env('BAHARI_PLATFORM_KEY'),
    'scopes' => env('BAHARI_SCOPES', 'openid profile email phone profile:read identity:read wallet:read platform:read'),
    // Only used while discovery is unreachable and nothing is cached.
    'admin_issuer' => env('BAHARI_ADMIN_ISSUER'),
    'discovery_ttl' => 300,
    'cache_store' => env('BAHARI_CACHE_STORE'),

    // Who signs in where. "path" is the area the middleware protects and the default return path.
    // "filament_panel" lets Livewire requests of that panel resolve to this kind.
    'kinds' => [
        'client' => ['guard' => 'web', 'path' => '/dashboard', 'filament_panel' => null],
        'admin' => ['guard' => 'admin', 'path' => '/admin', 'filament_panel' => null],
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'auth',          // /auth/{kind}/login, /auth/{kind}/callback, /auth/{kind}/logout, /auth/backchannel-logout
        'name' => 'bahari.',         // bahari.login, bahari.callback, bahari.logout, bahari.backchannel
        'middleware' => ['web'],
        // Adds GET /login (route "login") sending to Bahari sign-in when the app has no "login" route, so Laravel's
        // own auth redirects also go to Bahari. The app must not have its own login or registration.
        'login_alias' => true,
    ],

    // Session key prefix: the signed-in Bahari session id is kept at "{session_key}.{kind}".
    'session_key' => 'bahari.identity',
    'session_hours' => 8,
    'post_logout_redirect' => null,  // defaults to url('/')

    // One "Sign in" button (route bahari.signin): people whose Bahari account is linked to an administrator of this
    // platform continue to admin sign-in (Bahari asks for their admin password and 2FA) and land in the admin area.
    'auto_admin' => true,

    // Stores sessions and maps Bahari profiles to local users. The default uses the bahari_sessions table
    // (php artisan vendor:publish --tag=bahari-migrations) and needs a user_resolver.
    'session_repository' => Bahari\Laravel\EloquentSessionRepository::class,
    'user_resolver' => null,
];
