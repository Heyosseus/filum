<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class NotTheOwner extends RuntimeException
{
    public static function of(int $conversationId): self
    {
        return new self("Only the owner may do that to group {$conversationId}.");
    }
}
