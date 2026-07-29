# Contributing

Thanks for considering a contribution.

## Getting set up

```bash
composer install
composer test
```

You need PHP 8.3 or later, and PCOV or Xdebug — the suite enforces 100% line
coverage, so it cannot run without a coverage driver.

On PHP 8.4+ with Laravel 13, Composer resolves Pest 5 and you can use Test Impact
Analysis locally:

```bash
composer test:tia
```

The first run records a baseline; later runs replay everything your change did not
touch. TIA is deliberately not used in CI.

## What the gates check

`composer test` runs five in sequence:

| Gate | Command |
| --- | --- |
| Rector | `rector --dry-run` |
| Pint | `pint --test` |
| PHPStan | `phpstan analyse` (level 8) |
| Type coverage | `pest --type-coverage --min=100` |
| Line coverage | `pest --coverage --min=100` |

All five must pass. If a line is genuinely unreachable, delete it rather than
finding a way to exclude it — an untestable branch is usually a branch that should
not exist.

## House rules

- `declare(strict_types=1)` in every file; classes `final` unless there is a
  reason not to be.
- No user-facing string literals. Everything goes through
  `__('filum::filum.*')`, and both `lang/en` and `lang/ka` must stay complete —
  a test enforces matching keys and matching placeholders.
- **Test code must stay compatible with Pest 4.** Pest 5 only resolves on the
  Laravel 13 leg, and the Laravel 12 CI leg runs Pest 4. Avoid Pest-5-only syntax.
- Version differences between Filament 4 and 5 belong in `src/Support/Compat.php`
  and nowhere else.
- Avoid `#[Override]` on methods inherited from Filament or Livewire: the same
  method can differ between majors, and the attribute turns that into a fatal
  error. Rector is configured to skip `src/Pages` and `src/Livewire` for this
  reason.

## Reporting security issues

See [SECURITY.md](SECURITY.md). Please do not open a public issue.
