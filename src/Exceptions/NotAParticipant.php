<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class NotAParticipant extends RuntimeException
{
    public static function of(int $conversationId): self
    {
        return new self("The user does not take part in conversation {$conversationId}.");
    }
}
