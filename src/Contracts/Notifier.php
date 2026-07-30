<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Contracts;

use Heyosseus\Filum\Models\Message;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * How someone finds out about a message they were not on screen for.
 *
 * This is delivery only. Whether a notification is warranted at all is decided
 * in Messages, which holds the read state the decision turns on -- an
 * implementation is told who to tell and about what, and is trusted to say it.
 */
interface Notifier
{
    /**
     * Tell one recipient about a message that has already been persisted.
     *
     * Implementations must not throw. This runs on the send path, and a bell that
     * fails to ring is a smaller problem than a send button that appears broken.
     */
    public function messageSent(Message $message, Authenticatable $recipient): void;
}
