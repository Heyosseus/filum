<?php

declare(strict_types=1);

namespace Heyosseus\Filum;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Heyosseus\Filum\Pages\Chat;

/**
 * Filum, added to a Filament panel.
 *
 * It registers the Chat page and nothing else. Authorization is the page's own,
 * deferring to Filum::auth, so one gate governs the page, the overlay and the
 * broadcast channels alike.
 */
final class FilumPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'filum';
    }

    public function register(Panel $panel): void
    {
        if (! Filum::enabled()) {
            return;
        }

        $panel->pages([Chat::class]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
