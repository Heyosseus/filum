# Changelog

All notable changes to `heyosseus/filum` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0]

### Added

- Group conversations, joined by invitation. Any member may invite, anyone may
  leave, and only the owner may remove someone, rename or delete. An invitation is
  pending until accepted, so nobody is silently subscribed to a conversation.
- An invite picker on the group thread header: a disclosure listing colleagues not
  already invited or joined, so populating a group is a click from the thread
  itself rather than a separate screen. Someone who has left becomes invitable
  again from the same list.
- Invitations ring Filament's notification bell.
- `filum.groups.enabled` switches the whole feature off; disabled means absent.

### Fixed

- `ChatPanel::$conversation` is a public Livewire property, so a browser could set
  it to any conversation id and every read path — the thread, the group header,
  the member count, scrollback, the unread fingerprint — would render whatever it
  found. Writes and broadcasts were already gated by membership; reads were not.
  Every read path now goes through the same membership check, so a conversation
  the viewer has not joined renders nothing, the same as if it did not exist.

### Changed

- The bell now rings only for recipients who are **not currently present**.
  Somebody with the panel open already sees the unread count on the colleague, on
  the group and on the overlay tab, so a notification as well told them the same
  thing twice and accumulated a list to clear. "Present" is the same set the board
  shows as `HERE NOW`, so the two always agree; tune it with `presence.ttl`.
  Applies to invitations too.

- **Breaking.** `Notifier` gains `invited(Conversation $group, Authenticatable $recipient, Authenticatable $inviter): void`.
  Applications implementing the contract themselves must add it.
- **Breaking.** `Conversation::includes()` now means *joined*, not merely present.
  This is what keeps a pending invitee out of the thread, the send path and the
  broadcast channel. Anything relying on the old meaning changes behaviour.
- `ChatPanel` selects a conversation rather than a user, and board assembly moved
  to `Heyosseus\Filum\Board\Boards`.

### Upgrading

```bash
composer update heyosseus/filum
php artisan vendor:publish --tag=filum-migrations
php artisan migrate
```

Existing direct conversations are unaffected: the new columns default to `direct`
and `joined`.

## [0.1.1]

### Changed

- `filum.users.guard` now defaults to the panel's own guard instead of a hardcoded
  `web`. A panel authenticating its own model against its own guard needed no
  configuration to work; with the old default it asked Laravel for the `web`
  guard's model, and where that model does not exist the error surfaced while the
  navigation was being built — taking down every page in the panel, not just the
  chat. Name a guard explicitly to override.

### Added

- 1:1 chat between admin users, as a Filament page and as a slide-over overlay on
  every panel page.
- Broadcasting-agnostic delivery: a `Transport` contract with a `LaravelBroadcast`
  driver and a `Polling` fallback, selected automatically. No paid service is ever
  required.
- Reconciliation polling under a broadcaster, so a dropped socket heals itself.
- Presence: a database heartbeat as the single source of truth, with
  presence-channel events prompting an immediate re-read where available.
- Per-participant read state and unread badges on both the sidebar and the
  navigation item.
- Keyset scrollback through long threads.
- Configurable per-sender rate limit and maximum message length.
- `filum:install` command; publishable config, migrations, translations and views.
- English and Georgian translations, both complete.
- Support for Filament 4 and 5 (Livewire 3 and 4) behind a single `Compat` seam.
- Filament database notifications for messages the recipient was not on screen
  for, via a pluggable `Notifier` contract. One per conversation, on the
  transition from caught up to behind, so a burst rings once.

### Fixed

- The browser never subscribed to the events Filum broadcast, so configuring a
  broadcaster switched the poll to its slow reconciliation interval without
  putting anything faster in its place — real-time made it slower. The panel now
  listens on `window.Echo` when one is available.
- Polling re-rendered the component every few seconds whether or not anything had
  changed, and each re-render morphed the DOM out from under whoever was typing:
  the composer lost its contents and its caret. A tick that finds nothing new now
  renders nothing, and the textarea is left alone by the morph.
- `Load earlier messages` replaced the visible page instead of adding to it, so
  the newest messages disappeared when reaching back for context. Scrollback now
  only ever grows, and the button appears only when something precedes what is on
  screen.

- The stylesheet consumed Filament's colour tokens as RGB channel triplets, which
  is how Filament 3 published them. Filament 4 and 5 are built on Tailwind 4 and
  publish complete colour values, so every declaration holding one was invalid and
  silently dropped — the chat rendered with no surfaces, borders, avatars or
  buttons. Tokens are now read directly and translucency comes from `color-mix`.
- Migrations published without a date prefix, which sorted them ahead of every
  application migration and so created the foreign key to the users table before
  that table existed.
