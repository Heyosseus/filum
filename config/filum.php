<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When this is false Filum registers nothing at all: no Filament page, no
    | overlay, no broadcast channels, no commands. Disabled means absent rather
    | than present-and-declining, so there is no surface left to reach.
    |
    */

    'enabled' => env('FILUM_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Admin users
    |--------------------------------------------------------------------------
    |
    | Filum never assumes your user model or its key type. Migrations derive the
    | foreign key from the model named here, so integer, UUID and ULID keys all
    | work. The provider decides what an admin is called and what their avatar
    | is; replace it to read your own columns.
    |
    | Leave the guard empty and Filum follows whichever guard the panel itself
    | uses, which is almost always what you want. Name one only to override that.
    | The model has no such default, because migrations run with no panel in
    | sight and the foreign key type has to come from somewhere definite.
    |
    */

    'users' => [
        'model' => env('FILUM_USER_MODEL', 'App\\Models\\User'),
        'guard' => env('FILUM_GUARD'),
        'provider' => Heyosseus\Filum\Users\ConfiguredUserProvider::class,
        'name_column' => 'name',
        'avatar_column' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | "auto" uses your application's configured broadcaster when there is a real
    | one, and falls back to polling when there is not. That is what lets Filum
    | work immediately after composer require, with no paid service and no
    | WebSocket server. Set "broadcast" or "polling" to decide for yourself.
    |
    | Both intervals are seconds. The reconciliation interval is the slow poll
    | that stays on even under a broadcaster, so a dropped socket heals itself.
    |
    */

    'transport' => [
        'driver' => env('FILUM_TRANSPORT', 'auto'),
        'poll_interval' => 5,
        'reconcile_interval' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence
    |--------------------------------------------------------------------------
    |
    | The heartbeat is the only thing that ever writes presence. Under a real
    | broadcaster, presence-channel events merely trigger a re-read, so the
    | sidebar behaves identically whether or not one is available.
    |
    | A user counts as active while their last heartbeat falls inside the TTL.
    | Keep the TTL a small multiple of the interval so one missed beat is not
    | mistaken for a disconnect.
    |
    */

    'presence' => [
        'heartbeat_interval' => 60,
        'ttl' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    |
    | The rate limit is per sender, counted over the given window in seconds.
    |
    */

    'messages' => [
        'max_length' => 2000,
        'per_page' => 50,
        'rate_limit' => 30,
        'rate_window' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | A message to someone who is not looking rings Filament's own notification
    | bell, using the application's existing notifications table. No broadcaster
    | is needed: Filament polls the bell.
    |
    | One notification per conversation, sent when a recipient goes from caught up
    | to behind. A busy thread therefore rings once rather than forty times, and
    | rings again once they have caught up. Where the table has never been
    | migrated, or the user model is not notifiable, this quietly does nothing.
    |
    */

    'notifications' => [
        'enabled' => env('FILUM_NOTIFICATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Surfaces
    |--------------------------------------------------------------------------
    |
    | The page is Filum's home. The overlay puts the same chat behind a topbar
    | trigger on every panel page; switch it off where that is too invasive.
    |
    */

    'overlay' => [
        'enabled' => true,
    ],

    'navigation' => [
        'sort' => null,
        'group' => null,
    ],

];
