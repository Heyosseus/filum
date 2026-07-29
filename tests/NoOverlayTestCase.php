<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests;

use Illuminate\Contracts\Config\Repository;
use Override;

/**
 * A panel that keeps the chat page but declines the overlay.
 *
 * Same reasoning as DisabledTestCase: the render hook is registered at boot, so
 * switching the config off afterwards would prove nothing.
 */
abstract class NoOverlayTestCase extends FilamentTestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make(Repository::class)->set('filum.overlay.enabled', false);
    }
}
