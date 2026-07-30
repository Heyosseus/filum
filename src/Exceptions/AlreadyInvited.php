<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class AlreadyInvited extends RuntimeException
{
    public static function of(int $conversationId): self
    {
        return new self("That user is already in group {$conversationId} or waiting to accept.");
    }
}
