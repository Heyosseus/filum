<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A user whose key is neither an int nor a string.
 *
 * Nothing sane produces one, which is the point: Filum stores identifiers in a
 * database column and has to refuse what it cannot store rather than coerce it
 * into something surprising.
 */
final class OddlyKeyedUser implements Authenticatable
{
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * @return array<int, string>
     */
    public function getAuthIdentifier(): array
    {
        return ['not', 'a', 'key'];
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
