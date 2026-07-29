<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests;

use Heyosseus\Filum\Tests\Filament\FilumTestPanelProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ViewErrorBag;
use Override;

/**
 * Boots a real Filament panel with Filum's plugin on it, so the UI tests mount
 * the chat through the panel an application would have rather than a stub.
 */
abstract class FilamentTestCase extends TestCase
{
    /**
     * A component mounted straight from a test never runs the middleware that
     * shares the request's error bag, so the first thing a Blade view asks for --
     * $errors -- would be null. Sharing an empty bag gives every render something
     * real to read.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['session']->start();
    }

    /**
     * Testbench does not run package discovery, so everything Filament relies on
     * is named here by hand, in dependency order, and last the panel carrying
     * Filum's plugin.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider::class,

            // Filament must register before Livewire, as it does under real package
            // discovery. Filament rebinds Livewire's DataStore to a subclass with a
            // *non-shared* binding; it is Livewire's own mechanism registration,
            // running afterwards, that pins that subclass as a shared instance.
            // Reverse the two and every component resolves a fresh store, losing
            // its error bag between set and get.
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,

            \Livewire\LivewireServiceProvider::class,

            // Filum registers its Livewire component and render hook in boot, so
            // it must come after the packages it hangs those on.
            ...parent::getPackageProviders($app),

            FilumTestPanelProvider::class,
        ];
    }
}
