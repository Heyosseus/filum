<?php

declare(strict_types=1);

namespace Heyosseus\Filum;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Heyosseus\Filum\Http\DownloadAttachment;
use Heyosseus\Filum\Pages\Chat;
use Illuminate\Support\Facades\Route;

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

        // A panel route rather than a web one, deliberately. Inside the panel the
        // request carries the panel's own guard and tenant, so Filum resolves the
        // same "who is asking" here as it does in the chat itself -- which is the
        // whole basis on which the file is handed over or withheld.
        $panel->routes(static function (): void {
            Route::get('filum/attachments/{attachment}', DownloadAttachment::class)
                ->name('filum.attachment')
                ->whereNumber('attachment');
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
