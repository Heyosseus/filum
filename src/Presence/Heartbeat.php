<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Presence;

use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Contracts\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One beat: the user is here, and anyone watching should re-read the list.
 *
 * The announcement carries no state -- it only prompts a re-read of the store,
 * which is what keeps presence to a single source of truth.
 */
final readonly class Heartbeat
{
    public function __construct(
        private PresenceStore $store,
        private UserProvider $users,
        private Transport $transport,
    ) {}

    public function beat(Authenticatable $user): void
    {
        $id = $this->users->id($user);

        $wasActive = in_array($id, $this->store->active(), true);

        $this->store->beat($id);

        // Only announce the edge, not every beat.
        if (! $wasActive) {
            $this->transport->presenceChanged($id, true);
        }
    }
}
