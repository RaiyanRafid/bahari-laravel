<?php

namespace Bahari\Laravel;

use Bahari\Laravel\Contracts\SessionRepository;
use Bahari\Laravel\Contracts\StoredSession;
use Bahari\Sso\Client;
use Bahari\Sso\Exception\AccessDeniedException;
use Bahari\Sso\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Laravel entry point (facade "Bahari").
 */
class BahariManager
{
    public function __construct(private readonly Client $client, private readonly SessionRepository $sessions) {}

    public function client(): Client
    {
        return $this->client;
    }

    public function sessions(): SessionRepository
    {
        return $this->sessions;
    }

    /** A discovered URL, for example Bahari::url('client.profile_url') or Bahari::url('admin.panel_url'). */
    public function url(string $name, array $query = []): string
    {
        return $this->client->url($name, $query);
    }

    public function walletUrl(?string $returnTo = null): string
    {
        return $this->client->walletUrl($returnTo ?? config('bahari.kinds.client.path'));
    }

    /** The checked Bahari session of the current request (set by the bahari.auth middleware). */
    public function session(?Request $request = null): ?StoredSession
    {
        return ($request ?? request())->attributes->get('bahari_session');
    }

    public function navigation(StoredSession $session): array
    {
        return $this->client->navigation($session->getBahariKind(), $session->getBahariTokens(), config('bahari.kinds.client.path'));
    }

    public function updatePinned(StoredSession $session, array $keys): Response
    {
        return $this->client->updatePinned($session->getBahariTokens(), $keys);
    }

    /** Calls a platform API with this app's machine token, for example Bahari::api('GET', 'platform-wallet-balances'). */
    public function api(string $method, string $path, array $data = [], string $scope = 'identity:lookup'): array
    {
        return $this->client->api($method, $path, $data, $scope);
    }

    /** Billing calls (createInvoice, createSubscription, cancelSubscription, ...) go to the core client. */
    public function __call(string $method, array $arguments): mixed
    {
        abort_unless(in_array($method, ['createInvoice', 'invoice', 'voidInvoice', 'createSubscription', 'subscription', 'updateSubscription', 'cancelSubscription', 'resumeSubscription', 'customerSubscriptions'], true), 500, 'Unknown Bahari method '.$method.'.');

        return $this->client->{$method}(...$arguments);
    }

    /**
     * Rechecks a stored session with Bahari (rotating client tokens when needed) and saves the result.
     *
     * @throws AccessDeniedException when the session must end.
     */
    public function check(StoredSession $session): StoredSession
    {
        $recheck = (int) config('bahari.session_recheck_seconds', 0);
        $expiresAt = $session->getBahariAccessExpiresAt();
        $store = Cache::store(config('bahari.cache_store'));
        if ($recheck > 0 && (! $expiresAt || $expiresAt->getTimestamp() - time() > 45) && $store->has('bahari:checked:'.$session->getBahariSessionId())) {
            return $session;
        }

        return Cache::lock('bahari-session:'.$session->getBahariSessionId(), 60)->block(3, function () use ($session, $recheck, $store): StoredSession {
            $fresh = $this->sessions->find($session->getBahariKind(), $session->getBahariSessionId(), $session->getBahariUser());
            if (! $fresh) {
                throw new AccessDeniedException('The session has ended.');
            }
            $expiresAt = $fresh->getBahariAccessExpiresAt();
            if ($recheck > 0 && (! $expiresAt || $expiresAt->getTimestamp() - time() > 45) && $store->has('bahari:checked:'.$fresh->getBahariSessionId())) {
                return $fresh;
            }
            $identity = $this->client->refresh($fresh->getBahariKind(), $fresh->getBahariTokens(), $fresh->getBahariSubject(), $expiresAt ? $expiresAt->getTimestamp() - time() : null);
            $refreshed = $this->sessions->refreshed($fresh, $identity);
            if ($recheck > 0) {
                $store->put('bahari:checked:'.$fresh->getBahariSessionId(), true, $recheck);
            }

            return $refreshed;
        });
    }

    /** The kind ("client" or "admin") a request belongs to. */
    public function kindFor(Request $request): string
    {
        $kinds = config('bahari.kinds', []);
        foreach ($kinds as $kind => $settings) {
            $path = trim((string) ($settings['path'] ?? ''), '/');
            if ($path !== '' && $request->is($path, $path.'/*')) {
                return $kind;
            }
        }
        if (class_exists(\Filament\Facades\Filament::class)) {
            $panel = \Filament\Facades\Filament::getCurrentPanel()?->getId();
            foreach ($kinds as $kind => $settings) {
                if ($panel !== null && ($settings['filament_panel'] ?? null) === $panel) {
                    return $kind;
                }
            }
        }

        return 'client';
    }

    public function guard(string $kind): string
    {
        return (string) config("bahari.kinds.{$kind}.guard", $kind === 'admin' ? 'admin' : 'web');
    }

    public function sessionKey(string $kind): string
    {
        return config('bahari.session_key', 'bahari.identity').'.'.$kind;
    }

    public function route(string $name): string
    {
        return config('bahari.routes.name', 'bahari.').$name;
    }

    /**
     * A local return path inside the kind's area, or the area itself. Blocks external URLs and traversal.
     */
    public function safeReturnPath(string $kind, mixed $value): string
    {
        $prefix = '/'.trim((string) config("bahari.kinds.{$kind}.path", '/'), '/');
        if (! is_string($value) || strlen($value) > 2048 || ! preg_match('~^'.preg_quote(rtrim($prefix, '/'), '~').'(?:[/?]|$)~', $value) || preg_match('/[\\\\\x00-\x20\x7f]/', rawurldecode($value)) || str_contains(rawurldecode($value), '..') || str_starts_with($value, '//')) {
            return $prefix;
        }

        return $value;
    }
}
