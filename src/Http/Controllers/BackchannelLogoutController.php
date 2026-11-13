<?php

namespace Bahari\Laravel\Http\Controllers;

use Bahari\Laravel\Contracts\RevokesLinkedSessions;
use Bahari\Laravel\BahariManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Bahari posts a signed logout_token when a customer signs out anywhere; every local customer session of that account ends.
 */
class BackchannelLogoutController extends Controller
{
    public function __invoke(Request $request, BahariManager $bahari): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 65536, 413);
        $data = $request->validate(['logout_token' => 'required|string|max:20000']);
        try {
            $claims = $bahari->client()->verifyLogoutToken($data['logout_token']);
        } catch (Throwable) {
            abort(422, 'Invalid logout notification.');
        }
        $key = 'bahari-logout-jti:'.hash('sha256', (string) $claims['jti']);
        Cache::lock($key.':lock', 10)->block(2, function () use ($key, $claims, $bahari): void {
            if (Cache::has($key)) {
                return;
            }
            $bahari->sessions()->revokeSubject('client', $claims['sub']);
            // Bahari also sends this when someone loses admin access to the app: end their admin sessions too.
            if ($bahari->sessions() instanceof RevokesLinkedSessions) {
                $bahari->sessions()->revokeLinkedUser($claims['sub']);
            }
            Cache::put($key, true, 300);
        });

        return response()->json(['received' => true])->header('Cache-Control', 'no-store');
    }
}
