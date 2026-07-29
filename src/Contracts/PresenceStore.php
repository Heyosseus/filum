<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Contracts;

use Carbon\CarbonInterface;

/**
 * Who is around, and when they were last seen.
 *
 * This is the single source of truth for presence. Presence-channel events may
 * prompt a re-read, but they never write here -- one store, one set of rules,
 * and identical behaviour whether or not a broadcaster exists.
 */
interface PresenceStore
{
    /**
     * Record that a user is here now.
     */
    public function beat(int|string $userId): void;

    /**
     * The users whose last heartbeat falls inside the configured TTL.
     *
     * @return list<int|string>
     */
    public function active(): array;

    /**
     * When a user was last seen, or null if they never have been.
     */
    public function seenAt(int|string $userId): ?CarbonInterface;
}
