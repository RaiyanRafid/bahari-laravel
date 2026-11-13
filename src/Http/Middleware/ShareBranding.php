<?php

namespace Bahari\Laravel\Http\Middleware;

use Bahari\Laravel\BahariManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Shares the app's Bahari branding with every view as $bahariBranding and the maintenance state as
 * $bahariMaintenance (['enabled' => bool, 'message' => ?string]), so layouts can show the logo and a maintenance
 * banner. Cached by the SDK; never blocks the request when Bahari is unreachable.
 */
class ShareBranding
{
    public function __construct(private readonly BahariManager $bahari) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $branding = $this->bahari->client()->branding(null, (int) config('bahari.branding_ttl', 60));
        } catch (Throwable) {
            $branding = ['maintenance' => ['enabled' => false, 'message' => null]];
        }
        View::share('bahariBranding', $branding);
        View::share('bahariMaintenance', ['enabled' => (bool) ($branding['maintenance']['enabled'] ?? false), 'message' => $branding['maintenance']['message'] ?? null]);

        return $next($request);
    }
}
