<?php

declare(strict_types=1);

namespace Heyosseus\Filum;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Heyosseus\Filum\Console\Commands\InstallCommand;
use Heyosseus\Filum\Contracts\Notifier;
use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Livewire\ChatPanel;
use Heyosseus\Filum\Notifications\DatabaseNotifier;
use Heyosseus\Filum\Notifications\NullNotifier;
use Heyosseus\Filum\Presence\DatabasePresenceStore;
use Heyosseus\Filum\Support\Compat;
use Heyosseus\Filum\Transport\TransportManager;
use Heyosseus\Filum\Users\ConfiguredUserProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Override;

final class FilumServiceProvider extends ServiceProvider
{
    /**
     * Register Filum's services into the container.
     *
     * Bindings are registered unconditionally -- resolving a transport in a
     * disabled application should still give you a transport rather than an
     * error. It is the surfaces, in boot(), that the master switch removes.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filum.php', 'filum');

        $this->app->singleton(PresenceStore::class, DatabasePresenceStore::class);

        $this->app->singleton(UserProvider::class, function (Application $app): UserProvider {
            $configured = $app->make(Repository::class)->get('filum.users.provider');

            if (is_string($configured) && is_a($configured, UserProvider::class, true)) {
                /** @var UserProvider */
                return $app->make($configured);
            }

            return $app->make(ConfiguredUserProvider::class);
        });

        // The manager decides between broadcasting and polling once per request,
        // rather than at every call site that wants to announce something.
        $this->app->singleton(Transport::class, fn (Application $app): Transport => $app->make(TransportManager::class)->driver());

        // Switched off means bound to a notifier that does nothing, so the send
        // path never asks whether it should be telling anyone.
        $this->app->singleton(Notifier::class, fn (Application $app): Notifier => $app->make(Repository::class)->get('filum.notifications.enabled', true)
            ? $app->make(DatabaseNotifier::class)
            : $app->make(NullNotifier::class));
    }

    /**
     * Bootstrap translations, views, migrations and publishable assets.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filum');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filum');

        if (! Filum::enabled()) {
            // Disabled means absent: no channels, no commands, no publishing.
            return;
        }

        $this->registerChannels();
        $this->registerLivewire();
        $this->registerOverlay();
        $this->registerAssets();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../config/filum.php' => $this->app->configPath('filum.php'),
            ], 'filum-config');

            // Two registrations over the same directory, and the split is load
            // bearing rather than tidy. Laravel stamps a published migration with
            // the moment it was published, so a consumer's copy on disk is named
            // after that moment and not after the source. vendor:publish then tests
            // "already published?" against the *source* name, does not find it, and
            // copies the file again under a fresh stamp -- two create-tables
            // migrations, and a migrate that aborts on the second before it ever
            // reaches the new one.
            //
            // So: the whole directory for a fresh install, which has nothing on disk
            // to duplicate, and every schema change after 0.1.1 also under a tag of
            // its own, which is the only thing an existing install is ever told to
            // publish.
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'filum-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/2026_07_30_000000_add_group_conversations_to_filum_tables.php' => $this->app->databasePath('migrations/2026_07_30_000000_add_group_conversations_to_filum_tables.php'),
            ], 'filum-migrations-groups');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/2026_07_31_000000_create_filum_reactions_table.php' => $this->app->databasePath('migrations/2026_07_31_000000_create_filum_reactions_table.php'),
            ], 'filum-migrations-reactions');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/filum'),
            ], 'filum-translations');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/filum'),
            ], 'filum-views');
        }
    }

    /**
     * Enrol the chat as a Livewire component under a stable name.
     *
     * Registered here rather than in the plugin's boot so that both surfaces --
     * the page and the overlay -- can mount it, and so the name does not depend
     * on which Livewire major derived it.
     */
    private function registerLivewire(): void
    {
        if (! $this->app->bound('livewire')) {
            return;
        }

        Livewire::component('filum.chat-panel', ChatPanel::class);
    }

    /**
     * Hang the overlay off every panel page.
     *
     * The overlay is the point of an in-panel chat -- reading a message should
     * not mean leaving what you were doing -- but it is also the invasive part,
     * so it answers to its own config switch.
     */
    private function registerOverlay(): void
    {
        if (! (bool) $this->app->make(Repository::class)->get('filum.overlay.enabled', true)) {
            return;
        }

        FilamentView::registerRenderHook(
            Compat::bodyEndHook(),
            static fn (): string => Filum::authorized()
                ? Blade::render('@livewire(\'filum.chat-panel\', [\'mode\' => \'overlay\'])')
                : '',
        );
    }

    /**
     * Filum's stylesheet, shipped compiled so consumers run no build step.
     *
     * No class_exists guard around Filament here or in registerOverlay: Filament
     * is a hard requirement, so an install without it cannot happen, and a guard
     * against it would be a branch no test could ever enter.
     */
    private function registerAssets(): void
    {
        FilamentAsset::register([
            Css::make('filum', __DIR__.'/../resources/dist/filum.css'),
        ], 'heyosseus/filum');
    }

    /**
     * Filum's broadcast channels.
     *
     * Loaded from the package rather than published, because their authorization
     * is not the application's to weaken: both callbacks defer to the same
     * Filum::auth gate the UI uses.
     */
    private function registerChannels(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/channels.php');
    }
}
