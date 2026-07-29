<?php

declare(strict_types=1);

use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Tests\Fixtures\User;

it('names a user from the configured column', function (): void {
    expect(app(UserProvider::class)->name($this->user('Nino')))->toBe('Nino');
});

it('falls back to the email when there is no name', function (): void {
    $user = User::query()->create(['name' => null, 'email' => 'dato@example.com']);

    expect(app(UserProvider::class)->name($user))->toBe('dato@example.com');
});

it('falls back to the key when there is neither', function (): void {
    $user = User::query()->create(['name' => null, 'email' => null]);

    expect(app(UserProvider::class)->name($user))->toBe('#'.$user->id);
});

it('has no avatar unless a column is configured', function (): void {
    expect(app(UserProvider::class)->avatar($this->user('Nino')))->toBeNull();
});

it('reads an avatar from the configured column', function (): void {
    config()->set('filum.users.avatar_column', 'email');

    expect(app(UserProvider::class)->avatar($this->user('Nino')))->toBe('nino@example.com');
});

it('lists everyone except the person asking', function (): void {
    $nino = $this->user('Nino');
    $this->user('Giorgi');
    $this->user('Dato');

    $others = app(UserProvider::class)->chattable($nino);

    expect($others)->toHaveCount(2)
        ->and($others->pluck('name')->all())->toBe(['Dato', 'Giorgi']);
});

it('finds a user by key and returns null for a stranger', function (): void {
    $nino = $this->user('Nino');

    expect(app(UserProvider::class)->find($nino->id)?->getAuthIdentifier())->toBe($nino->id)
        ->and(app(UserProvider::class)->find(9999))->toBeNull();
});

it('reports a user key', function (): void {
    $nino = $this->user('Nino');

    expect(app(UserProvider::class)->id($nino))->toBe($nino->id);
});
