<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class NotAGroup extends RuntimeException
{
    public static function of(int $conversationId): self
    {
        return new self("Conversation {$conversationId} is not a group.");
    }

    public static function disabled(): self
    {
        return new self('Filum group conversations are switched off.');
    }
}
