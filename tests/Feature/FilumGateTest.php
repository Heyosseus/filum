<?php

declare(strict_types=1);

use Heyosseus\Filum\Filum;

it('admits any authenticated user by default', function (): void {
    expect(Filum::authorized($this->user('Nino')))->toBeTrue();
});

it('refuses nobody in particular', function (): void {
    expect(Filum::authorized())->toBeFalse();
});

it('honours a registered gate', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    Filum::auth(fn ($user): bool => $user?->getAuthIdentifier() === $nino->id);

    expect(Filum::authorized($nino))->toBeTrue()
        ->and(Filum::authorized($giorgi))->toBeFalse();
});

it('refuses everyone when the master switch is off', function (): void {
    config()->set('filum.enabled', false);

    expect(Filum::authorized($this->user('Nino')))->toBeFalse()
        ->and(Filum::enabled())->toBeFalse();
});

it('reads the authenticated user from the configured guard', function (): void {
    $nino = $this->user('Nino');

    expect(Filum::user())->toBeNull();

    $this->actingAs($nino);

    expect(Filum::user()?->getAuthIdentifier())->toBe($nino->id)
        ->and(Filum::authorized())->toBeTrue();
});

it('honours an explicitly named guard over anything it could infer', function (): void {
    config()->set('filum.users.guard', 'web');

    $nino = $this->user('Nino');
    $this->actingAs($nino, 'web');

    expect(Filum::user()?->getAuthIdentifier())->toBe($nino->id);
});

it('falls back to the default guard with no panel and no configured guard', function (): void {
    // A console command or a queued job: nothing to follow, so Laravel's own
    // default applies rather than a guard Filum invented.
    config()->set('filum.users.guard');

    $nino = $this->user('Nino');
    $this->actingAs($nino);

    expect(Filum::user()?->getAuthIdentifier())->toBe($nino->id);
});

it('ignores a configured guard that is not a guard name', function (): void {
    config()->set('filum.users.guard', '');

    $nino = $this->user('Nino');
    $this->actingAs($nino);

    expect(Filum::user()?->getAuthIdentifier())->toBe($nino->id);
});
