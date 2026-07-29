<?php

declare(strict_types=1);

namespace Heyosseus\Filum;

use Closure;
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
        $guard = Config::get('filum.users.guard', 'web');

        return Auth::guard(is_string($guard) ? $guard : 'web')->user();
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
