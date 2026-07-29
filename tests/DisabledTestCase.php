<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests;

use Illuminate\Contracts\Config\Repository;
use Override;

/**
 * Filum switched off before it boots.
 *
 * The master switch has to be read at boot rather than flipped afterwards,
 * because "disabled" means Filum registered nothing in the first place -- and a
 * config change made inside a test arrives far too late to prove that.
 */
abstract class DisabledTestCase extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make(Repository::class)->set('filum.enabled', false);
    }
}
