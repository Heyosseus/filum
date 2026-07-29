<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests\Filament;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Heyosseus\Filum\FilumPlugin;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Override;

/**
 * The panel an application would have, with Filum on it.
 */
final class FilumTestPanelProvider extends PanelProvider
{
    #[Override]
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('filum-test')
            ->path('filum-test')
            ->pages([Dashboard::class])
            ->middleware([
                EncryptCookies::class,
                ConvertEmptyStringsToNull::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugin(FilumPlugin::make());
    }
}
