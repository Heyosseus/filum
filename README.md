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
- Filament 4.11.5+ or 5.6.5+

The Filament floor is a security minimum, not a preference: earlier 4.x and 5.x
releases carry published advisories, so Filum will not resolve against them.

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
php artisan reverb:install   # also installs Echo and sets BROADCAST_CONNECTION
php artisan reverb:start
npm run build                # Echo has to reach the browser
```

Filum needs no further configuration — it reads whatever
`config/broadcasting.php` already says and switches by itself.

**Both halves have to be in place.** Filum starts pushing as soon as a real
broadcaster is configured, and the browser starts listening as soon as
`window.Echo` exists in the panel. If you configure a broadcaster but never build
an Echo client into your assets, the socket half is missing and the
reconciliation poll is all that is left — which is *slower* than the no-broadcaster
default, because it is 30 seconds rather than 5. `reverb:install` sets up both;
if you wire a broadcaster up by hand, make sure Echo is actually loaded on the
page.

To decide for yourself:

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

## Notifications

A message to someone who is not looking rings Filament's own notification bell,
writing into the application's existing `notifications` table. No broadcaster
needed — Filament polls the bell.

It rings **once per conversation**, on the transition from caught up to behind. A
forty-message burst is one bell entry, not forty; catching up and falling behind
again earns a fresh one.

It also rings only for a recipient who is **not currently present**. Someone with
the panel open already sees the unread counter change on the colleague, on the
group, and on the overlay tab, so a bell as well would only tell them what they
can already see. "Present" is exactly the set the board shows as `HERE NOW`, so
the bell and the board never disagree — tune it with `presence.ttl`, below. This
applies to invitations too: inviting someone who is at their desk right now grows
the invitations section for them on the next tick and never rings.

```php
'notifications' => [
    'enabled' => env('FILUM_NOTIFICATIONS', true),
],
```

Where the `notifications` table has never been migrated, or your user model is
not `Notifiable`, this quietly does nothing — the message still sends. To deliver
somewhere else entirely (mail, Slack, a pager), implement
`Heyosseus\Filum\Contracts\Notifier` and bind it:

```php
$this->app->singleton(Heyosseus\Filum\Contracts\Notifier::class, MyNotifier::class);
```

## Groups

Beyond 1:1 chat, colleagues can form a group. Joining is always by invitation:
creating a group only seats the creator, who becomes its owner; everyone else
appears in the invitations section of their own board and has to accept before
the thread, the send path or the broadcast channel will admit them. Declining
and leaving land on the same state, so a colleague who said no and a colleague
who walked away look identical to the group afterwards.

Permissions are flat with one exception for the owner:

- **Any member** may invite a colleague.
- **Anyone** may leave.
- **Only the owner** may remove someone else, rename the group, or delete it.

An owner does not remove themselves — they leave, like anyone else. When they
do, ownership passes automatically to whoever has been joined the longest (ties
broken by who joined first, i.e. the lower participant id). If nobody is left
joined, the group is deleted rather than left ownerless.

The group's thread header carries an invite picker: a disclosure listing
colleagues who are neither invited nor already joined, so growing the group is a
click from the thread itself. Someone who has left reappears in that list —
leaving does not blacklist a person, it just returns them to "not yet invited."

```php
'groups' => [
    'enabled' => env('FILUM_GROUPS', true),
],
```

Disabled means absent, the same as the panel-wide switch below: no groups
section on the board, no new-group action, existing groups unreachable, and
every group action refused — not a feature that half-works underneath a
switched-off UI.

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

`ttl` is also what decides whether the notification bell rings, above — and it
is deliberately the only knob for that decision. It would be easy to add a
second, shorter threshold just for notifications ("ring if silent for 30s even
if the board still shows them as here"), but that threshold could then disagree
with what the board displays: a colleague marked `HERE NOW` who nonetheless gets
a bell, which reads as a bug even though it would be working as configured. One
number, shared by both, means the bell and the board can never contradict each
other — the honest cost is that `ttl`'s default of 180 seconds means someone who
closes their laptop and is written to thirty seconds later still counts as
present, and gets no bell for it. Lower `ttl` if that lag matters more to you
than a slightly twitchier "here" indicator; there is no separate way to tune one
without the other.

## Your user model

Filum assumes nothing about your schema:

```php
'users' => [
    'model' => App\Models\AdminUser::class,
    'guard' => null,           // null follows the panel's own guard
    'name_column' => 'name',
    'avatar_column' => null,   // e.g. 'avatar_url'
    'provider' => Heyosseus\Filum\Users\ConfiguredUserProvider::class,
],
```

**`model` is the one thing you must set** if your panel does not authenticate
`App\Models\User`. Filum queries it to list colleagues and derives the migration's
foreign key from it, and neither can be guessed — a panel on its own guard has its
own user model. Set it before you migrate:

```dotenv
FILUM_USER_MODEL=App\Models\AdminUser
```

Unquoted, and note it goes in every environment's `.env`, not just your local one.
Leaving it wrong is not a quiet failure: Filum is asked for the current user while
the navigation is being built, so an unresolvable model surfaces as an error on
every page in the panel rather than only on the chat.

`guard` needs no value — Filum follows whichever guard the panel uses. Name one
only to override that.

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
