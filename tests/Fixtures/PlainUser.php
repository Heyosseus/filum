<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user model without the Notifiable trait.
 *
 * Perfectly ordinary: notifications are a thing an application opts into, and a
 * panel whose users have never been notified of anything has no reason to carry
 * the trait. Filum must find the bell missing rather than call a method that is
 * not there.
 */
final class PlainUser extends Authenticatable
{
    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = ['name', 'email'];

    public $timestamps = false;
}
