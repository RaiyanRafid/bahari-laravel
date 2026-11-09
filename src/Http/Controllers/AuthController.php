<?php

namespace Bahari\Laravel\Http\Controllers;

use Bahari\Laravel\BahariManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly BahariManager $bahari) {}

    /**
     * One sign-in for everyone: customers stay customers; linked administrators continue to admin sign-in.
     */
    public function signin(Request $request): RedirectResponse
    {
        return $this->begin($request, 'client', ['auto' => (bool) config('bahari.auto_admin', true)]);
    }

    /**
     * "Create account": Bahari registration, then back into the app like a sign-in.
     */
    public function register(Request $request): RedirectResponse
    {
        return $this->begin($request, 'client', ['auto' => (bool) config('bahari.auto_admin', true), 'prompt' => 'create']);
    }

    public function login(Request $request, string $kind): RedirectResponse
    {
        $extra = [];
        if ($kind === 'admin') {
            // Set by an auto sign-in: the admin account must belong to the customer who just signed in.
            $expected = $request->session()->pull(self::EXPECTED_ADMIN);
            if (is_array($expected) && is_string($expected['user_id'] ?? null) && time() - (int) ($expected['at'] ?? 0) <= 600) {
                $extra = ['expected_user_id' => $expected['user_id'], 'login_hint' => $expected['email'] ?? null];
            }
        }

        return $this->begin($request, $kind, $extra);
    }

    private const EXPECTED_ADMIN = 'bahari_expected_admin';

    private function begin(Request $request, string $kind, array $extra): RedirectResponse
    {
        abort_unless($this->bahari->client()->config()->configured(), 503, 'Bahari sign-in is being configured.');
        $login = $this->bahari->client()->beginLogin($kind, route($this->bahari->route('callback'), ['kind' => $kind]), $extra['login_hint'] ?? null, $extra['prompt'] ?? null);
        unset($extra['login_hint'], $extra['prompt']);
        $request->session()->put('bahari_oauth.'.hash('sha256', $login->state), $login->transaction + $extra + ['return_to' => $this->bahari->safeReturnPath($kind, $request->query('return_to'))]);

        return redirect()->away($login->url)->header('Cache-Control', 'private, no-store');
    }

    public function callback(Request $request, string $kind): RedirectResponse
    {
        $data = $request->validate(['state' => 'required|regex:/^[a-f0-9]{64}$/D', 'code' => 'nullable|string|max:20000', 'error' => 'nullable|string|max:100']);
        $transaction = $request->session()->pull('bahari_oauth.'.hash('sha256', $data['state']));
        abort_unless(is_array($transaction) && ($transaction['kind'] ?? null) === $kind && time() - (int) ($transaction['created_at'] ?? 0) <= 600, 422, 'Sign-in expired. Please start again.');
        abort_if($request->filled('error') || ! $request->filled('code'), 422, 'Sign-in was not approved.');
        try {
            $identity = $this->bahari->client()->completeLogin($transaction, $data['code']);
            if ($kind === 'admin' && isset($transaction['expected_user_id']) && ($identity->profile['user_id'] ?? null) !== $transaction['expected_user_id']) {
                // Signed in to Bahari admin as someone else: keep the customer session only.
                Log::notice('Bahari admin sign-in skipped: the admin account is not linked to the signed-in customer.');

                return redirect()->to($this->bahari->safeReturnPath('client', null))->with('bahari.notice', 'You are signed in as a customer. The administrator account you used belongs to someone else.');
            }
            $session = $this->bahari->sessions()->create($identity);
            Auth::guard($this->bahari->guard($kind))->login($session->getBahariUser());
            $request->session()->regenerate();
            $request->session()->put($this->bahari->sessionKey($kind), $session->getBahariSessionId());
            if ($kind === 'client' && ($transaction['auto'] ?? false) && $this->bahari->client()->adminAccess($identity) && array_key_exists('admin', config('bahari.kinds', []))) {
                $request->session()->put(self::EXPECTED_ADMIN, ['user_id' => $identity->subject, 'email' => $identity->profile['email'] ?? null, 'at' => time()]);

                return redirect()->route($this->bahari->route('login'), ['kind' => 'admin', 'return_to' => $this->bahari->safeReturnPath('admin', null)])->header('Cache-Control', 'private, no-store');
            }
        } catch (Throwable $exception) {
            Log::warning('Bahari sign-in failed', ['kind' => $kind, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
            abort(503, 'Sign-in could not be completed. Please start again or contact support.');
        }

        return redirect()->to($transaction['return_to'] ?? $this->bahari->safeReturnPath($kind, null))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function logout(Request $request, string $kind): RedirectResponse
    {
        $guard = $this->bahari->guard($kind);
        $id = $request->session()->pull($this->bahari->sessionKey($kind));
        $user = Auth::guard($guard)->user();
        $session = $user && is_string($id) ? $this->bahari->sessions()->find($kind, $id, $user) : null;
        if ($session) {
            \Illuminate\Support\Facades\Cache::store(config('bahari.cache_store'))->forget('bahari:checked:'.$session->getBahariSessionId());
            $this->bahari->sessions()->revoke($session);
            try {
                $this->bahari->client()->revoke($kind, $session->getBahariTokens());
            } catch (Throwable $exception) {
                Log::notice('Bahari session revoke failed; the local session is ended.', ['kind' => $kind, 'exception' => $exception::class]);
            }
        }
        Auth::guard($guard)->logout();
        $request->session()->regenerateToken();

        return redirect()->away($this->bahari->client()->logoutUrl($kind, config('bahari.post_logout_redirect') ?: url('/'), $session?->getBahariTokens()['id_token'] ?? null));
    }
}
