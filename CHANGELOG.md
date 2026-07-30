# Changelog

All notable changes to `heyosseus/filum` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
