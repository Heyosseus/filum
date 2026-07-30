<?php

declare(strict_types=1);

namespace Heyosseus\Filum;

use Closure;
use Filament\Facades\Filament;
use Filament\FilamentManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

/**
 * Filum's entry point: one gate, and the user it is asked about.
 *
 * Every surface -- the page, the overlay, the broadcast channels -- funnels its
 * authorization through here, so a user can never subscribe to something they
 * cannot see. Registering a callback in a service provider replaces the default,
 * which admits anyone the panel already authenticated.
 */
final class Filum
{
    /**
     * The application's own answer to "may this person use the chat?".
     *
     * @var (Closure(Authenticatable|null): bool)|null
     */
    private static ?Closure $auth = null;

    /**
     * Decide who may use the chat.
     *
     * @param  Closure(Authenticatable|null): bool  $callback
     */
    public static function auth(Closure $callback): void
    {
        self::$auth = $callback;
    }

    /**
     * Whether the given user -- the authenticated one by default -- may use the chat.
     *
     * Being authenticated is a precondition rather than a permission: the default
     * gate admits any authenticated user, because the panel has already decided
     * who may reach it at all.
     */
    public static function authorized(?Authenticatable $user = null): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $user ??= self::user();

        if (! $user instanceof Authenticatable) {
            return false;
        }

        if (self::$auth instanceof Closure) {
            return (bool) (self::$auth)($user);
        }

        return true;
    }

    /**
     * The user Filum is acting as, from the configured guard.
     */
    public static function user(): ?Authenticatable
    {
        return Auth::guard(self::guard())->user();
    }

    /**
     * The guard Filum authenticates against.
     *
     * Unset means "whichever guard the panel itself uses", and that is the right
     * default rather than a convenience. A hardcoded 'web' is wrong for most
     * panels: an admin panel with its own guard has its own user model, and
     * resolving the web guard there asks Laravel for a class the application may
     * not have. Filum is asked for the user while the *navigation* is being
     * built, so that failure does not break the chat -- it breaks the sidebar,
     * and with it every page in the panel.
     *
     * Outside a panel -- a console command, a queued job -- there is no panel to
     * follow, and null lets Laravel use the application's default guard.
     */
    private static function guard(): ?string
    {
        $configured = Config::get('filum.users.guard');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // Asked of the container rather than assumed: Filum is only ever installed
        // alongside Filament, but it is not only ever *called* from inside a panel.
        if (! app()->bound(FilamentManager::class)) {
            return null;
        }

        return Filament::getCurrentPanel()?->getAuthGuard();
    }

    /**
     * The master switch.
     */
    public static function enabled(): bool
    {
        return (bool) Config::get('filum.enabled', true);
    }

    /**
     * Forget the registered gate. Used by tests to isolate one from the next.
     */
    public static function flush(): void
    {
        self::$auth = null;
    }
}
