<?php

namespace Bahari\Laravel\Http\Middleware;

use Bahari\Laravel\BahariManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Requires a live Bahari session (alias "bahari.auth", optionally "bahari.auth:admin"). Each request is rechecked with
 * Bahari, so suspended accounts, removed admin roles and revoked sessions end at once; any failure signs the person out.
 */
class Authenticate
{
    public function __construct(private readonly BahariManager $bahari) {}

    public function handle(Request $request, Closure $next, ?string $kind = null): Response
    {
        $kind ??= $this->bahari->kindFor($request);
        $guard = $this->bahari->guard($kind);
        Auth::shouldUse($guard);
        $user = Auth::guard($guard)->user();
        $id = $request->session()->get($this->bahari->sessionKey($kind));
        $stored = $user && is_string($id) ? $this->bahari->sessions()->find($kind, $id, $user) : null;
        if ($stored) {
            try {
                $request->attributes->set('bahari_session', $this->bahari->check($stored));

                return $next($request);
            } catch (Throwable) {
                $this->bahari->sessions()->revoke($stored);
            }
        }
        Auth::guard($guard)->logout();
        $request->session()->forget($this->bahari->sessionKey($kind));
        if ($request->expectsJson()) {
            abort(401, 'Bahari sign-in is required.');
        }

        return redirect()->route($this->bahari->route('login'), ['kind' => $kind, 'return_to' => $request->getPathInfo().($request->getQueryString() ? '?'.$request->getQueryString() : '')]);
    }
}
