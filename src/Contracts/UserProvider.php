<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * What Filum knows about an admin.
 *
 * Filum never assumes your user model's columns. Replace the configured
 * implementation to read your own -- a display name assembled from two columns,
 * an avatar from a media library, a restricted set of chattable colleagues.
 */
interface UserProvider
{
    /**
     * The name to show for a user.
     */
    public function name(Authenticatable $user): string;

    /**
     * A URL to the user's avatar, or null to fall back to their initials.
     */
    public function avatar(Authenticatable $user): ?string;

    /**
     * Everyone who may be chatted with, excluding the given user.
     *
     * @return Collection<int, Authenticatable>
     */
    public function chattable(Authenticatable $except): Collection;

    /**
     * One user by key, or null when they no longer exist.
     */
    public function find(int|string $id): ?Authenticatable;

    /**
     * A user's primary key.
     */
    public function id(Authenticatable $user): int|string;
}
