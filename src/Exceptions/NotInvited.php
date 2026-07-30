<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class NotInvited extends RuntimeException
{
    public static function of(int $conversationId): self
    {
        return new self("There is no pending invitation to group {$conversationId}.");
    }
}
