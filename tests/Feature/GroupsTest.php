<?php

declare(strict_types=1);

use Heyosseus\Filum\Exceptions\NotAGroup;
use Heyosseus\Filum\Exceptions\NotTheOwner;
use Heyosseus\Filum\Groups\Groups;
use Heyosseus\Filum\Models\Conversation;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->groups = app(Groups::class);
});

it('creates a group owned by whoever made it, with them already joined', function (): void {
    $group = $this->groups->create($this->nino, '  Couriers  ');

    expect($group->kind)->toBe('group')
        ->and($group->name)->toBe('Couriers')
        ->and($group->owner_id)->toBe($this->nino->id)
        ->and($group->key)->toBeNull()
        ->and($group->includes($this->nino->id))->toBeTrue();
});

it('refuses a group with no name', function (): void {
    $this->groups->create($this->nino, '   ');
})->throws(InvalidArgumentException::class);

it('bounds a very long name rather than refusing it', function (): void {
    $group = $this->groups->create($this->nino, str_repeat('a', 200));

    expect(mb_strlen((string) $group->name))->toBe(120);
});

it('lets the owner rename the group', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->rename($group, $this->nino, 'Couriers East');

    expect($group->fresh()?->name)->toBe('Couriers East');
});

it('refuses a rename by anyone but the owner', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->rename($group, $this->giorgi, 'Mine Now');
})->throws(NotTheOwner::class);

it('refuses to treat a direct conversation as a group', function (): void {
    $direct = Conversation::query()->create(['kind' => 'direct', 'key' => 'abc']);

    $this->groups->rename($direct, $this->nino, 'Not A Group');
})->throws(NotAGroup::class);

it('refuses to create a group when groups are switched off', function (): void {
    config()->set('filum.groups.enabled', false);

    $this->groups->create($this->nino, 'Couriers');
})->throws(NotAGroup::class);
