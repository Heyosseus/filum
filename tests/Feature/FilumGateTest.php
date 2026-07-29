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
