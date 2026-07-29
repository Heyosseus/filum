<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The application's user, as far as the tests are concerned. Filum never sees
 * this class -- it only ever sees what config names.
 */
final class User extends Authenticatable
{
    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = ['name', 'email'];

    public $timestamps = false;
}
