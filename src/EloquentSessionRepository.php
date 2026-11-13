<?php

namespace Bahari\Laravel;

use Bahari\Laravel\Contracts\RevokesLinkedSessions;
use Bahari\Laravel\Contracts\SessionRepository;
use Bahari\Laravel\Contracts\StoredSession;
use Bahari\Laravel\Contracts\UserResolver;
use Bahari\Laravel\Models\BahariSession;
use Bahari\Sso\Identity;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Default storage: the bahari_sessions table plus the app's UserResolver (config bahari.user_resolver).
 */
class EloquentSessionRepository implements RevokesLinkedSessions, SessionRepository
{
    public function create(Identity $identity): StoredSession
    {
        $user = $this->resolver()->resolve($identity->kind, $identity->profile);
        $session = BahariSession::query()->create([
            'kind' => $identity->kind, 'subject' => $identity->subject, 'user_id' => (string) $user->getAuthIdentifier(), 'tokens' => $identity->tokens, 'profile' => $identity->profile,
            'access_expires_at' => $identity->accessExpiresIn ? now()->addSeconds($identity->accessExpiresIn) : null, 'expires_at' => now()->addHours((int) config('bahari.session_hours', 8)),
        ]);
        $session->resolvedUser = $user;

        return $session;
    }

    public function find(string $kind, string $id, Authenticatable $user): ?StoredSession
    {
        $session = BahariSession::query()->whereKey($id)->where('kind', $kind)->where('user_id', (string) $user->getAuthIdentifier())->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if ($session) {
            $session->resolvedUser = $user;
        }

        return $session;
    }

    public function refreshed(StoredSession $session, Identity $identity): StoredSession
    {
        /** @var BahariSession $session */
        $session->forceFill(['profile' => $identity->profile] + ($identity->tokensChanged ? ['tokens' => $identity->tokens, 'access_expires_at' => now()->addSeconds($identity->accessExpiresIn ?? 900)] : []))->save();

        return $session;
    }

    public function revoke(StoredSession $session): void
    {
        \Illuminate\Support\Facades\Cache::store(config('bahari.cache_store'))->forget('bahari:checked:'.$session->getBahariSessionId());
        BahariSession::query()->whereKey($session->getBahariSessionId())->update(['revoked_at' => now()]);
    }

    public function revokeSubject(string $kind, string $subject): void
    {
        $query = BahariSession::query()->where('kind', $kind)->where('subject', $subject)->whereNull('revoked_at');
        $ids = (clone $query)->pluck('id');
        foreach ($ids as $id) {
            \Illuminate\Support\Facades\Cache::store(config('bahari.cache_store'))->forget('bahari:checked:'.$id);
        }
        $query->update(['revoked_at' => now()]);
    }

    public function revokeLinkedUser(string $userId): void
    {
        // Profiles are encrypted, so the (few) active admin sessions are checked one by one.
        BahariSession::query()->where('kind', 'admin')->whereNull('revoked_at')->where('expires_at', '>', now())->get()
            ->filter(fn (BahariSession $session): bool => ($session->getBahariProfile()['user_id'] ?? null) === $userId)
            ->each(function (BahariSession $session): void {
                \Illuminate\Support\Facades\Cache::store(config('bahari.cache_store'))->forget('bahari:checked:'.$session->id);
                $session->forceFill(['revoked_at' => now()])->save();
            });
    }

    private function resolver(): UserResolver
    {
        $class = config('bahari.user_resolver');
        if (! $class) {
            throw new RuntimeException('Set bahari.user_resolver to a class implementing '.UserResolver::class.'.');
        }

        return app($class);
    }
}
