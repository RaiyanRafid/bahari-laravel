<?php

namespace Bahari\Laravel\Contracts;

use Bahari\Sso\Identity;
use Illuminate\Contracts\Auth\Authenticatable;

interface SessionRepository
{
    /** Creates or updates the local user for this identity and saves a new session. */
    public function create(Identity $identity): StoredSession;

    /** The active (not revoked, not expired) session of this user and kind, or null. */
    public function find(string $kind, string $id, Authenticatable $user): ?StoredSession;

    /** Saves the rechecked profile (and the tokens when $identity->tokensChanged). */
    public function refreshed(StoredSession $session, Identity $identity): StoredSession;

    public function revoke(StoredSession $session): void;

    /** Ends every active session of this Bahari account (back-channel logout). */
    public function revokeSubject(string $kind, string $subject): void;
}
