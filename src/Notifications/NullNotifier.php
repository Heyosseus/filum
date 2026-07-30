<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Notifications;

use Heyosseus\Filum\Contracts\Notifier;
use Heyosseus\Filum\Models\Message;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Notifications switched off.
 *
 * Bound in place of the database notifier rather than guarded at the call site,
 * so the send path has one shape whatever the configuration says.
 */
final readonly class NullNotifier implements Notifier
{
    public function messageSent(Message $message, Authenticatable $recipient): void
    {
        //
    }
}
