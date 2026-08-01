<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Exceptions;

use RuntimeException;

final class AttachmentRefused extends RuntimeException
{
    public static function tooLarge(string $name, int $maxKilobytes): self
    {
        return new self("{$name} is larger than the {$maxKilobytes} KB this application accepts.");
    }

    public static function wrongType(string $name): self
    {
        return new self("{$name} is not a kind of file this application accepts.");
    }

    public static function tooMany(int $max): self
    {
        return new self("A message may carry at most {$max} files.");
    }

    public static function disabled(): self
    {
        return new self('Filum attachments are switched off.');
    }
}
