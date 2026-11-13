<?php

namespace Bahari\Laravel\Contracts;

/**
 * Optional for session repositories: ends the admin sessions that belong to a Bahari account. Back-channel logout
 * names the person's account (sub); admin sessions are stored under the administrator id, with the account in the
 * profile's user_id. Without this, admin sessions still end at their next check with Bahari.
 */
interface RevokesLinkedSessions
{
    public function revokeLinkedUser(string $userId): void;
}
