<?php

declare(strict_types=1);

use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Exceptions\NotAParticipant;
use Heyosseus\Filum\Exceptions\RateLimited;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    $this->messages = app(Messages::class);
});

it('persists a message and stamps the conversation', function (): void {
    $message = $this->messages->send($this->conversation, $this->nino, 'გამარჯობა');

    expect($message->body)->toBe('გამარჯობა')
        ->and($message->sender_id)->toBe($this->nino->id)
        ->and($this->conversation->fresh()?->last_message_at)->not->toBeNull();
});

it('keeps the message when the broadcaster is down', function (): void {
    Log::spy();

    $exploding = new class implements Transport
    {
        public function messageSent(Message $message): void
        {
            throw new RuntimeException('reverb is down');
        }

        public function presenceChanged(int|string $userId, bool $online): void {}

        public function descriptor(): array
        {
            return ['driver' => 'broadcast', 'poll' => 30];
        }
    };

    app()->instance(Transport::class, $exploding);

    // A broadcaster outage must not reach the person typing. The row is already
    // written by the time announcing happens, so the failure is logged and the
    // reconciliation poll delivers what the socket missed.
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'still here');

    expect($message->body)->toBe('still here')
        ->and(Message::query()->where('body', 'still here')->exists())->toBeTrue();

    Log::shouldHaveReceived('warning')->once();
});

it('refuses a sender who is not in the conversation', function (): void {
    $outsider = $this->user('Dato');

    $this->messages->send($this->conversation, $outsider, 'let me in');
})->throws(NotAParticipant::class);

it('refuses an empty message', function (): void {
    $this->messages->send($this->conversation, $this->nino, '   ');
})->throws(InvalidArgumentException::class);

it('truncates a message past the configured maximum', function (): void {
    config()->set('filum.messages.max_length', 10);

    $message = $this->messages->send($this->conversation, $this->nino, str_repeat('a', 50));

    expect(mb_strlen($message->body))->toBe(10);
});

it('rate limits a sender who floods', function (): void {
    config()->set('filum.messages.rate_limit', 3);

    foreach (range(1, 3) as $i) {
        $this->messages->send($this->conversation, $this->nino, "message {$i}");
    }

    $this->messages->send($this->conversation, $this->nino, 'one too many');
})->throws(RateLimited::class);

it('does not rate limit when the limit is switched off', function (): void {
    config()->set('filum.messages.rate_limit', 0);

    foreach (range(1, 25) as $i) {
        $this->messages->send($this->conversation, $this->nino, "message {$i}");
    }

    expect(Message::query()->count())->toBe(25);
});

it('pages backwards through a long thread by keyset', function (): void {
    config()->set('filum.messages.rate_limit', 0);
    config()->set('filum.messages.per_page', 10);

    foreach (range(1, 25) as $i) {
        $this->messages->send($this->conversation, $this->nino, "message {$i}");
    }

    $newest = $this->messages->page($this->conversation);

    expect($newest)->toHaveCount(10)
        ->and($newest->first()?->body)->toBe('message 16')
        ->and($newest->last()?->body)->toBe('message 25');

    $older = $this->messages->page($this->conversation, $newest->first()?->id);

    expect($older)->toHaveCount(10)
        ->and($older->last()?->body)->toBe('message 15');
});

it('finds only what arrived after a given message', function (): void {
    config()->set('filum.messages.rate_limit', 0);

    $first = $this->messages->send($this->conversation, $this->nino, 'one');
    $this->messages->send($this->conversation, $this->giorgi, 'two');
    $this->messages->send($this->conversation, $this->giorgi, 'three');

    expect($this->messages->since($this->conversation, $first->id)->pluck('body')->all())
        ->toBe(['two', 'three']);
});

it('counts what the other person has not read', function (): void {
    config()->set('filum.messages.rate_limit', 0);

    $this->messages->send($this->conversation, $this->nino, 'one');
    $this->messages->send($this->conversation, $this->nino, 'two');

    expect($this->messages->unreadIn($this->conversation, $this->giorgi))->toBe(2)
        ->and($this->messages->unreadIn($this->conversation, $this->nino))->toBe(0);
});

it('clears the count once read', function (): void {
    config()->set('filum.messages.rate_limit', 0);

    $this->messages->send($this->conversation, $this->nino, 'one');
    $this->messages->markRead($this->conversation, $this->giorgi);

    expect($this->messages->unreadIn($this->conversation, $this->giorgi))->toBe(0);
});

it('counts nothing for someone outside the conversation', function (): void {
    $outsider = $this->user('Dato');

    expect($this->messages->unreadIn($this->conversation, $outsider))->toBe(0);
});

it('totals unread across every conversation', function (): void {
    config()->set('filum.messages.rate_limit', 0);

    $dato = $this->user('Dato');
    $other = app(Conversations::class)->between($dato->id, $this->giorgi->id);

    $this->messages->send($this->conversation, $this->nino, 'one');
    $this->messages->send($other, $dato, 'two');

    expect($this->messages->unreadTotal($this->giorgi))->toBe(2);
});

it('marking read is harmless for an outsider', function (): void {
    $outsider = $this->user('Dato');

    $this->messages->markRead($this->conversation, $outsider);
})->throwsNoExceptions();

it('pages an empty conversation without complaint', function (): void {
    $empty = Conversation::query()->create(['key' => 'lonely']);

    expect($this->messages->page($empty))->toHaveCount(0);
});
