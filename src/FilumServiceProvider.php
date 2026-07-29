<?php

declare(strict_types=1);

namespace Heyosseus\Filum;

use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Presence\DatabasePresenceStore;
use Heyosseus\Filum\Transport\TransportManager;
use Heyosseus\Filum\Users\ConfiguredUserProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
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

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filum.php' => $this->app->configPath('filum.php'),
            ], 'filum-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'filum-migrations');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/filum'),
            ], 'filum-translations');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/filum'),
            ], 'filum-views');
        }
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
