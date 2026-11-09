<?php

namespace Bahari\Laravel\Models;

use Bahari\Laravel\Contracts\StoredSession;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BahariSession extends Model implements StoredSession
{
    use HasUuids;

    protected $table = 'bahari_sessions';

    protected $guarded = ['id'];

    protected $hidden = ['tokens', 'profile'];

    protected function casts(): array
    {
        return ['tokens' => 'encrypted:array', 'profile' => 'encrypted:array', 'access_expires_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public ?Authenticatable $resolvedUser = null;

    public function getBahariSessionId(): string
    {
        return (string) $this->getKey();
    }

    public function getBahariKind(): string
    {
        return $this->kind;
    }

    public function getBahariSubject(): string
    {
        return $this->subject;
    }

    public function getBahariTokens(): array
    {
        return $this->tokens ?? [];
    }

    public function getBahariProfile(): array
    {
        return $this->profile ?? [];
    }

    public function getBahariUser(): Authenticatable
    {
        return $this->resolvedUser;
    }

    public function getBahariAccessExpiresAt(): ?DateTimeInterface
    {
        return $this->access_expires_at;
    }
}
