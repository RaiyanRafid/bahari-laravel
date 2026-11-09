<?php

namespace Bahari\Laravel\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A Bahari session saved by the app. Tokens must be stored encrypted.
 */
interface StoredSession
{
    public function getBahariSessionId(): string;

    public function getBahariKind(): string;

    public function getBahariSubject(): string;

    public function getBahariTokens(): array;

    public function getBahariProfile(): array;

    public function getBahariUser(): Authenticatable;

    public function getBahariAccessExpiresAt(): ?DateTimeInterface;
}
