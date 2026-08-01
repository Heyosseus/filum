# Changelog

All notable changes to `heyosseus/filum` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0]

### Added

- Replies. Any message can answer another, and the answer carries a one-line
  quote of what it answers. A reply may only point at something in the same
  conversation -- the id comes from the browser, and quoting across would carry a
  message's text into a thread not allowed to see it. Deleting the parent leaves
  the answer standing and drops only its quote.
- File attachments. A message can carry files, or be nothing but files -- typing
  "here" above every document is not a requirement. Images show as thumbnails,
  everything else as a named chip with its size.
- `filum.attachments` configures the disk, the size ceiling, how many files a
  message may carry, and the accepted types. The type is read from the file's own
  bytes rather than from what the browser claimed, so renaming a script to `.png`
  does not get it past the allowlist.

### Fixed

- Opening a conversation left the thread scrolled to the top, so the newest
  message was off screen until you scrolled down. A thread now opens at its foot
  and follows new messages -- but only while you are already at the bottom, so
  reading yesterday is never interrupted by a colleague typing today.

### Security

- Attachments are stored on a **private** disk and served through a panel route
  that checks conversation membership on every request, never linked to directly.
  A public URL would make every document somebody sends readable by anyone who
  guessed the path. The route answers 404 rather than 403 throughout, because
  whether a given file exists is itself something only a participant should learn.
  Keep `filum.attachments.disk` pointed at a private disk.

### Upgrading

```bash
composer update heyosseus/filum
php artisan vendor:publish --tag=filum-migrations-replies
php artisan migrate
```

Publish **only** that tag. Publishing `filum-migrations` on an existing install
copies the earlier migrations a second time under fresh timestamps and `migrate`
then aborts on the duplicate.

## [0.3.1]

### Fixed

- The reaction opener was a `<details>` element whose disclosure triangle painted
  anyway: `display: grid` on a `summary` stops it being a list item, so
  `list-style: none` no longer applies. It rendered as a stray arrow beside the
  opener on every message.
- An opener sat under every message permanently. In a long thread that is one
  piece of chrome per line competing with the record, which is the opposite of
  what a scannable log wants. Reactions somebody left are content and still
  always show; the control for leaving one is now revealed on hover, on keyboard
  focus, and persistently on touch devices, where there is no hover to reveal it.

### Changed

- The emoji set opens inline in the reaction row rather than as a popover. A
  popover inside a scrolling thread needs clipping and stacking handled, and it
  covers the very messages being reacted to.
- Reaction counts now use the same tabular monospace face as the time gutter, and
  your own reaction uses the accent your own messages already use.
- The `<details>` markup is gone, replaced by Alpine, which Filament already
  loads. No new dependency and no build step.

**Note for anyone styling Filum:** the CSS class `filum-reaction-pick` is now
`filum-reaction-add`, and the surrounding markup changed. If you published the
views with `--tag=filum-views` you keep your own copy and will not pick this up;
re-publish with `--force` or port the change by hand.

## [0.3.0]

### Added

- Emoji reactions on a message, as a toggle: tapping the same emoji twice takes
  it back, so there is no separate remove control to find. Several people can add
  the same emoji and one person can add several different ones.
- `filum.reactions.emoji` names the set an application offers, and anything
  outside it is refused rather than stored. A fixed set rather than a picker is
  deliberate: Filum ships compiled CSS and no build step, and a picker would mean
  a JavaScript bundle for something a back office uses six of.
- `filum.reactions.enabled` switches the feature off; disabled means absent, and
  with no emoji in the set every reaction is refused by the same guard that
  rejects a typo.

### Upgrading

```bash
composer update heyosseus/filum
php artisan vendor:publish --tag=filum-migrations-reactions
php artisan migrate
```

Publish **only** that tag. Publishing `filum-migrations` on an existing install
copies the earlier migrations a second time under fresh timestamps -- Laravel
looks for the source name and your install has the stamped one -- and `migrate`
then aborts on the duplicate. Run the tag once; do not put `vendor:publish` in a
repeatedly-executed deploy step.

## [0.2.0]

### Added

- Group conversations, joined by invitation. Any member may invite, anyone may
  leave, and only the owner may remove someone, rename or delete. An invitation is
  pending until accepted, so nobody is silently subscribed to a conversation.
- An invite picker on the group thread header: a disclosure listing colleagues not
  already invited or joined, so populating a group is a click from the thread
  itself rather than a separate screen. Someone who has left becomes invitable
  again from the same list.
- Rename and a member roster on the group thread header, for the owner: a mistyped
  group name and a colleague invited by mistake are both fixable from the product.
  The same control withdraws a pending invitation and removes a joined member, and
  the roster never offers one against the owner — an owner leaves instead, which
  passes the group on.
- Invitations ring Filament's notification bell.
- `filum.groups.enabled` switches the whole feature off; disabled means absent.

### Fixed

- `ChatPanel::$conversation` is a public Livewire property, so a browser could set
  it to any conversation id and every read path — the thread, the group header,
  the member count, scrollback, the unread fingerprint — would render whatever it
  found. Writes and broadcasts were already gated by membership; reads were not.
  Every read path now goes through the same membership check, so a conversation
  the viewer has not joined renders nothing, the same as if it did not exist.

- `filum.groups.enabled` promised that a disabled install has no reachable groups,
  and for reads and sends that was not true: a joined member could still open a
  group by id, read the thread, and send messages that broadcast — while being
  unable to leave, because leaving was the one action that checked. The switch is
  now enforced at the same read seam as membership, and in `Messages::send()`, so a
  group is genuinely absent while it is off. Nothing is deleted; switching it back
  on restores what was there.

- `Groups::invite()` accepted a user id that resolved to nobody, writing a pending
  invitation that could never be accepted, never rang, and — with no removal
  control — could never be withdrawn. It now refuses one before anything is written.

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
php artisan vendor:publish --tag=filum-migrations-groups
php artisan migrate
```

**Publish `filum-migrations-groups`, not `filum-migrations`.** The broad tag is for
a fresh install only. Laravel stamps a published migration with the moment it was
published, so the copy of `create_filum_tables` already on your disk is named after
your 0.1.1 install rather than after Filum's source file. `vendor:publish` looks
for the source name, does not find it, and copies the file again under a fresh
stamp — leaving you with two create-tables migrations. `migrate` then runs the
duplicate first, hits *table already exists*, aborts, and never reaches the group
migration. Every schema change after 0.1.1 gets a tag of its own for exactly this
reason.

If you have already done it, delete the newer duplicate of
`*_create_filum_tables.php` from `database/migrations` before migrating.

**Migrate as part of the deploy, not after it.** Between 0.2.0's code going live
and `migrate` finishing, chat is down — not degraded, down. `Conversations::between()`
writes `kind`, `state` and `joined_at`, and `Conversation::includes()` reads
`state`, so on a schema that lacks those columns every conversation fails,
including the 1:1 ones that worked a moment earlier. Run the migration in the same
release step that ships the code.

Existing direct conversations are unaffected once the migration has run: the new
columns default to `direct` and `joined`.

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
