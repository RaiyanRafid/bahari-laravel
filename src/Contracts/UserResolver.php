<?php

namespace Bahari\Laravel\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Finds or creates the local user for a Bahari profile (used by the default session repository).
 * $kind is "client" or "admin"; keep the two apart (different guards or a type column).
 */
interface UserResolver
{
    public function resolve(string $kind, array $profile): Authenticatable;
}
