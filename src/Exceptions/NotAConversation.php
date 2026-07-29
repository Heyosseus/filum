<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use InvalidArgumentException;

final class NotAConversation extends InvalidArgumentException
{
    public static function needsTwoPeople(): self
    {
        return new self('A conversation needs at least two distinct participants.');
    }
}
