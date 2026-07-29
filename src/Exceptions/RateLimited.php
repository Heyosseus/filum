<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class RateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Too many messages. Try again in {$retryAfter} seconds.");
    }
}
