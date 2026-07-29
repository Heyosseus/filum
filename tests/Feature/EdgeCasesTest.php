<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Conversations\ConversationKey;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Tests\Fixtures\OddlyKeyedUser;
use Heyosseus\Filum\Transport\TransportManager;
use Heyosseus\Filum\Users\ConfiguredUserProvider;
use Illuminate\Support\Facades\DB;

it('joins the conversation the other request already created', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $key = ConversationKey::for([$nino->id, $giorgi->id]);

    // Stand in for the request that got there first: the row already exists when
    // ours arrives. The unique index settles it, so we join rather than duplicate.
    DB::table('filum_conversations')->insert([
        'key' => $key,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = app(Conversations::class)->between($nino->id, $giorgi->id);

    expect($conversation->key)->toBe($key)
        ->and(Conversation::query()->where('key', $key)->count())->toBe(1)
        ->and($conversation->participants()->count())->toBe(2);
});

it('adds nobody twice when a conversation is reopened', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $conversations = app(Conversations::class);

    $conversations->between($nino->id, $giorgi->id);
    $conversation = $conversations->between($nino->id, $giorgi->id);

    expect($conversation->participants()->count())->toBe(2)
        ->and(Conversation::query()->count())->toBe(1);
});

it('falls back to the bundled user provider when config names something else', function (): void {
    config()->set('filum.users.provider', 'Not\\A\\Real\\Class');
    app()->forgetInstance(UserProvider::class);

    expect(app(UserProvider::class))->toBeInstanceOf(ConfiguredUserProvider::class);
});

it('refuses a user key it could not store', function (): void {
    app(UserProvider::class)->id(new OddlyKeyedUser);
})->throws(RuntimeException::class, 'The user identifier must be an int or a string.');

it('refuses a configured user model that is not a model', function (): void {
    config()->set('filum.users.model', 'Not\\A\\Real\\Model');

    app(UserProvider::class)->find(1);
})->throws(RuntimeException::class, 'filum.users.model must name an Eloquent model class.');

it('treats a broadcast connection name that is not a name as no broadcaster', function (): void {
    config()->set('broadcasting.default', false);

    expect(app(TransportManager::class)->name())->toBe('polling');

    config()->set('broadcasting.default', '');

    expect(app(TransportManager::class)->name())->toBe('polling');
});

it('counts only what arrived since the last read', function (): void {
    config()->set('filum.messages.rate_limit', 0);

    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');
    $conversation = app(Conversations::class)->between($nino->id, $giorgi->id);
    $messages = app(Messages::class);

    $messages->send($conversation, $giorgi, 'before');
    $messages->markRead($conversation, $nino);
    $messages->send($conversation, $giorgi, 'after');

    // Read state is a message id, not a timestamp. Timestamps are stored to the
    // second, so this message -- sent in the same second as the read above --
    // would be counted as already read if the comparison were by time.
    expect($messages->unreadTotal($nino))->toBe(1)
        ->and($messages->unreadIn($conversation, $nino))->toBe(1);
});

it('counts a message that arrives in the very same second as a read', function (): void {
    config()->set('filum.messages.rate_limit', 0);

    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');
    $conversation = app(Conversations::class)->between($nino->id, $giorgi->id);
    $messages = app(Messages::class);

    // Freeze the clock so read and arrival share a timestamp exactly.
    CarbonImmutable::setTestNow(CarbonImmutable::now());

    try {
        $messages->markRead($conversation, $nino);
        $messages->send($conversation, $giorgi, 'same second');

        expect($messages->unreadIn($conversation, $nino))->toBe(1);
    } finally {
        CarbonImmutable::setTestNow();
    }
});
