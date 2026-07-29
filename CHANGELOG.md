# Changelog

All notable changes to `heyosseus/filum` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
