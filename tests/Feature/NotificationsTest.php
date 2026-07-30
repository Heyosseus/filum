<?php

declare(strict_types=1);

use Heyosseus\Filum\Contracts\Notifier;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Notifications\DatabaseNotifier;
use Heyosseus\Filum\Notifications\NullNotifier;
use Heyosseus\Filum\Tests\Fixtures\PlainUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
});

it('rings the bell for the recipient and not for the sender', function (): void {
    app(Messages::class)->send($this->conversation, $this->nino, 'the invoice is on your desk');

    expect(bell($this->giorgi->id))->toHaveCount(1)
        ->and(bell($this->nino->id))->toBeEmpty();
});

it('names the sender and quotes what they said', function (): void {
    app(Messages::class)->send($this->conversation, $this->nino, 'the invoice is on your desk');

    $notification = bell($this->giorgi->id)[0];

    expect($notification['title'])->toBe('Nino')
        ->and($notification['body'])->toBe('the invoice is on your desk');
});

it('shortens a long message to one line', function (): void {
    app(Messages::class)->send($this->conversation, $this->nino, "line one\n\n     line two ".str_repeat('a', 200));

    $body = (string) bell($this->giorgi->id)[0]['body'];

    // Collapsed to a single line, and bounded: the bell is not a reading surface.
    expect($body)->toStartWith('line one line two ')
        ->and(mb_strlen($body))->toBe(120);
});

it('rings once for a burst rather than once per message', function (): void {
    $messages = app(Messages::class);

    $messages->send($this->conversation, $this->nino, 'one');
    $messages->send($this->conversation, $this->nino, 'two');
    $messages->send($this->conversation, $this->nino, 'three');

    // Already behind after the first: three more entries would bury the bell.
    expect(bell($this->giorgi->id))->toHaveCount(1);
});

it('rings again once the recipient has caught up', function (): void {
    $messages = app(Messages::class);

    $messages->send($this->conversation, $this->nino, 'before');
    $messages->markRead($this->conversation, $this->giorgi);
    $messages->send($this->conversation, $this->nino, 'after');

    expect(bell($this->giorgi->id))->toHaveCount(2);
});

it('stays quiet when notifications are switched off', function (): void {
    config()->set('filum.notifications.enabled', false);
    app()->forgetInstance(Notifier::class);

    expect(app(Notifier::class))->toBeInstanceOf(NullNotifier::class);

    app(Messages::class)->send($this->conversation, $this->nino, 'nobody hears this');

    expect(bell($this->giorgi->id))->toBeEmpty()
        ->and(Message::query()->count())->toBe(1);

    $group = app(Heyosseus\Filum\Groups\Groups::class)->create($this->nino, 'Couriers');
    app(Heyosseus\Filum\Groups\Groups::class)->invite($group, $this->nino, $this->giorgi->id);

    expect(bell($this->giorgi->id))->toBeEmpty()
        ->and($group->participants()->where('user_id', $this->giorgi->id)->exists())->toBeTrue();
});

it('resolves the database notifier when notifications are on', function (): void {
    expect(app(Notifier::class))->toBeInstanceOf(DatabaseNotifier::class);
});

it('stays quiet when the notifications table was never migrated', function (): void {
    Schema::drop('notifications');

    app(Messages::class)->send($this->conversation, $this->nino, 'no table to write to');

    // The message still lands; only the bell is unavailable.
    expect(Message::query()->count())->toBe(1);
});

it('stays quiet when the user model cannot be notified', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'not notifiable, not a crash');

    $plain = PlainUser::query()->create(['name' => 'Ada', 'email' => 'ada@example.com']);

    app(DatabaseNotifier::class)->messageSent($message, $plain);

    expect(DB::table('notifications')->where('notifiable_id', $plain->getKey())->count())->toBe(0);
});

it('keeps the message when a notifier throws', function (): void {
    Log::spy();

    app()->instance(Notifier::class, new class implements Notifier
    {
        public function messageSent(Message $message, Authenticatable $recipient): void
        {
            throw new RuntimeException('the pager exploded');
        }

        public function invited(Heyosseus\Filum\Models\Conversation $group, Authenticatable $recipient, Authenticatable $inviter): void {}
    });

    app(Messages::class)->send($this->conversation, $this->nino, 'sent regardless');

    expect(Message::query()->count())->toBe(1);

    Log::shouldHaveReceived('warning')->once();
});

it('rings the bell for someone invited to a group', function (): void {
    $group = app(Heyosseus\Filum\Groups\Groups::class)->create($this->nino, 'Couriers');

    app(Heyosseus\Filum\Groups\Groups::class)->invite($group, $this->nino, $this->giorgi->id);

    $notification = bell($this->giorgi->id)[0];

    expect($notification['title'])->toBe('Nino')
        ->and($notification['body'])->toBe(__('filum::filum.notification.invited_body', ['group' => 'Couriers']));
});

it('keeps the invitation when the bell cannot be rung', function (): void {
    Schema::drop('notifications');

    $group = app(Heyosseus\Filum\Groups\Groups::class)->create($this->nino, 'Couriers');
    app(Heyosseus\Filum\Groups\Groups::class)->invite($group, $this->nino, $this->giorgi->id);

    // The invitation is the thing that matters; the bell is a courtesy.
    expect($group->participants()->where('user_id', $this->giorgi->id)->exists())->toBeTrue();
});

it('keeps the invitation when a notifier throws', function (): void {
    Log::spy();

    app()->instance(Notifier::class, new class implements Notifier
    {
        public function messageSent(Message $message, Authenticatable $recipient): void {}

        public function invited(Heyosseus\Filum\Models\Conversation $group, Authenticatable $recipient, Authenticatable $inviter): void
        {
            throw new RuntimeException('the pager exploded');
        }
    });

    $groups = app(Heyosseus\Filum\Groups\Groups::class);
    $group = $groups->create($this->nino, 'Couriers');
    $groups->invite($group, $this->nino, $this->giorgi->id);

    expect($group->participants()->where('user_id', $this->giorgi->id)->exists())->toBeTrue();

    Log::shouldHaveReceived('warning')->once();
});
