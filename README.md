# Filum

Real-time chat between admin users, inside your Filament panel.

Click a colleague in the sidebar, the conversation opens, history persists, and
messages arrive as they are sent. Available as a full page and as a slide-over
overlay on every panel page.

**Broadcasting-agnostic, and free.** Filum works in an application that has never
configured broadcasting, and it never requires a paid service. If you have a
broadcaster — Reverb, soketi, Pusher, Ably — Filum uses it. If you don't, it
falls back to polling and the chat still works. Nothing in `require` pulls a
hosted WebSocket vendor.

Dark-first, following your panel's light/dark state. English and Georgian
included.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 4 or 5

## Installation

```bash
composer require heyosseus/filum
php artisan filum:install
php artisan migrate
```

Then add the plugin to your panel provider:

```php
use Heyosseus\Filum\FilumPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(FilumPlugin::make());
}
```

That's it. You now have a **Chat** page in the navigation and a chat trigger on
every page. No broadcaster required.

## Real-time

Filum picks a transport automatically:

| Your application | What Filum does |
| --- | --- |
| No broadcaster configured | Polls every 5 seconds |
| A real broadcaster configured | Pushes over it, and reconciles by polling every 30 seconds |

The reconciliation poll stays on under a broadcaster on purpose: a dropped
socket, a missed event, or a laptop waking from sleep heals itself instead of
leaving someone reading a stale thread.

To go real-time with no third party, install Reverb:

```bash
composer require laravel/reverb
php artisan reverb:install
php artisan reverb:start
```

Filum needs no further configuration — it uses whatever
`config/broadcasting.php` already says. To decide for yourself:

```php
// config/filum.php
'transport' => [
    'driver' => 'polling', // 'auto' (default), 'broadcast', or 'polling'
],
```

## Who can use it

By default, anyone the panel has already authenticated. To narrow it, register a
gate in a service provider:

```php
use Heyosseus\Filum\Filum;

Filum::auth(fn ($user) => $user->hasRole('admin'));
```

One gate governs everything — the page, the overlay, and the broadcast channels —
so nobody can subscribe to a conversation the page would not have shown them.

## Presence

The sidebar shows who is around. A heartbeat writes `last_seen_at` on an
interval, and that store is the only source of truth. Under a broadcaster,
presence-channel events additionally prompt an immediate re-read, so the sidebar
updates instantly — but it behaves identically without one, just with up to a
minute of latency.

```php
'presence' => [
    'heartbeat_interval' => 60, // seconds between beats
    'ttl' => 180,               // how long a beat counts as "here"
],
```

## Your user model

Filum assumes nothing about your schema:

```php
'users' => [
    'model' => App\Models\User::class,
    'guard' => 'web',
    'name_column' => 'name',
    'avatar_column' => null,   // e.g. 'avatar_url'
    'provider' => Heyosseus\Filum\Users\ConfiguredUserProvider::class,
],
```

Integer, UUID and ULID keys all work — migrations derive the foreign key from the
model you name. For anything more involved (a name assembled from two columns, an
avatar from a media library, a restricted set of chattable colleagues), implement
`Heyosseus\Filum\Contracts\UserProvider` and name it as the `provider`.

## Language

English and Georgian ship complete. Publish to change or add:

```bash
php artisan vendor:publish --tag=filum-translations
```

## Switching it off

```php
'enabled' => false,
```

Disabled means absent: no page, no overlay, no channels, no commands. Not a chat
that turns people away.

To keep the page but drop the overlay:

```php
'overlay' => ['enabled' => false],
```

## Testing

```bash
composer test          # rector, pint, phpstan, type coverage, line coverage
composer test:unit     # tests with coverage
composer test:tia      # tests via Pest's Test Impact Analysis (local, PHP 8.4+)
```

Both transports are covered by the suite, including the case with no broadcaster
bound at all — the install Filum promises to work in.

## Credits

- [heyosseus](https://github.com/heyosseus)

## License

MIT. See [LICENSE.md](LICENSE.md).
