<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class UnknownEmoji extends RuntimeException
{
    public static function of(string $emoji): self
    {
        return new self("{$emoji} is not one of the reactions this application offers.");
    }
}
