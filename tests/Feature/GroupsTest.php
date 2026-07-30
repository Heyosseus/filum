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

it('invites a colleague as pending, not as a member', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $participant = $this->groups->invite($group, $this->nino, $this->giorgi->id);

    expect($participant->state)->toBe('invited')
        ->and($participant->invited_by_id)->toBe($this->nino->id)
        ->and($participant->joined_at)->toBeNull()
        ->and($group->includes($this->giorgi->id))->toBeFalse();
});

it('lets any member invite, not only the owner', function (): void {
    $dato = $this->user('Dato');
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->invite($group, $this->nino, $this->giorgi->id);
    $this->groups->accept($group, $this->giorgi);

    $this->groups->invite($group, $this->giorgi, $dato->id);

    expect($group->fresh()?->participants()->where('user_id', $dato->id)->exists())->toBeTrue();
});

it('refuses an invitation from someone outside the group', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->invite($group, $this->giorgi, $this->user('Dato')->id);
})->throws(Heyosseus\Filum\Exceptions\NotAParticipant::class);

it('refuses to invite someone already invited', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);

    $this->groups->invite($group, $this->nino, $this->giorgi->id);
})->throws(Heyosseus\Filum\Exceptions\AlreadyInvited::class);

it('refuses to invite someone already joined', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->invite($group, $this->nino, $this->nino->id);
})->throws(Heyosseus\Filum\Exceptions\AlreadyInvited::class);

it('joins the group on accepting', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);

    $this->groups->accept($group, $this->giorgi);

    expect($group->includes($this->giorgi->id))->toBeTrue();

    $row = $group->participants()->where('user_id', $this->giorgi->id)->first();

    expect($row?->state)->toBe('joined')
        ->and($row?->joined_at)->not->toBeNull();
});

it('lands a declined invitation on the same state as leaving', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);

    $this->groups->decline($group, $this->giorgi);

    $row = $group->participants()->where('user_id', $this->giorgi->id)->first();

    expect($row?->state)->toBe('left')
        ->and($group->includes($this->giorgi->id))->toBeFalse();
});

it('refuses to accept without a pending invitation', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->accept($group, $this->giorgi);
})->throws(Heyosseus\Filum\Exceptions\NotInvited::class);

it('refuses to decline without a pending invitation', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->decline($group, $this->giorgi);
})->throws(Heyosseus\Filum\Exceptions\NotInvited::class);

it('re-invites someone who left, resuming where they had read to', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);
    $this->groups->accept($group, $this->giorgi);

    // Put them in the terminal state directly: leave() arrives in Task 4, and this
    // test is about what a re-invite does to an existing row, not how it got there.
    $group->participants()
        ->where('user_id', $this->giorgi->id)
        ->update(['state' => 'left', 'last_read_message_id' => 7]);

    $again = $this->groups->invite($group, $this->nino, $this->giorgi->id);

    expect($again->state)->toBe('invited')
        ->and($again->last_read_message_id)->toBe(7);
});

it('lets a member leave', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);
    $this->groups->accept($group, $this->giorgi);

    $this->groups->leave($group, $this->giorgi);

    expect($group->includes($this->giorgi->id))->toBeFalse();
});

it('refuses to leave a group you are not in', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->leave($group, $this->giorgi);
})->throws(Heyosseus\Filum\Exceptions\NotAParticipant::class);

it('passes ownership to the longest-joined member when the owner leaves', function (): void {
    $dato = $this->user('Dato');
    $group = $this->groups->create($this->nino, 'Couriers');

    foreach ([$this->giorgi, $dato] as $invitee) {
        $this->groups->invite($group, $this->nino, $invitee->id);
        $this->groups->accept($group, $invitee);
    }

    $this->groups->leave($group, $this->nino);

    // Giorgi accepted first, so the group is not left without anyone who can
    // rename or manage it.
    expect($group->fresh()?->owner_id)->toBe($this->giorgi->id);
});

it('deletes the group when the last member leaves', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $id = $group->id;

    $this->groups->leave($group, $this->nino);

    expect(Conversation::query()->find($id))->toBeNull();
});

it('lets the owner remove someone', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);
    $this->groups->accept($group, $this->giorgi);

    $this->groups->remove($group, $this->nino, $this->giorgi->id);

    expect($group->includes($this->giorgi->id))->toBeFalse();
});

it('lets the owner withdraw a pending invitation', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);

    $this->groups->remove($group, $this->nino, $this->giorgi->id);

    $row = $group->participants()->where('user_id', $this->giorgi->id)->first();

    expect($row?->state)->toBe('left');
});

it('refuses a removal by anyone but the owner', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);
    $this->groups->accept($group, $this->giorgi);

    $this->groups->remove($group, $this->giorgi, $this->nino->id);
})->throws(NotTheOwner::class);

it('sends the owner to leave rather than removing themselves', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->remove($group, $this->nino, $this->nino->id);
})->throws(InvalidArgumentException::class);

it('refuses to remove somebody who is not in the group', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');

    $this->groups->remove($group, $this->nino, $this->giorgi->id);
})->throws(Heyosseus\Filum\Exceptions\NotAParticipant::class);

it('lets the owner delete the group, and its messages with it', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $id = $group->id;

    $this->groups->delete($group, $this->nino);

    expect(Conversation::query()->find($id))->toBeNull()
        ->and(Heyosseus\Filum\Models\Participant::query()->where('conversation_id', $id)->exists())->toBeFalse();
});

it('refuses a deletion by anyone but the owner', function (): void {
    $group = $this->groups->create($this->nino, 'Couriers');
    $this->groups->invite($group, $this->nino, $this->giorgi->id);
    $this->groups->accept($group, $this->giorgi);

    $this->groups->delete($group, $this->giorgi);
})->throws(NotTheOwner::class);
