<?php

declare(strict_types=1);

use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Events\MessageSent;
use Heyosseus\Filum\Events\PresenceChanged;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Transport\LaravelBroadcast;
use Heyosseus\Filum\Transport\Polling;
use Heyosseus\Filum\Transport\TransportManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

it('falls back to polling when there is no broadcaster', function (): void {
    expect(app(TransportManager::class)->name())->toBe('polling')
        ->and(app(Transport::class))->toBeInstanceOf(Polling::class);
});

it('uses broadcasting when the application has a real broadcaster', function (): void {
    $this->withBroadcaster();

    expect(app(TransportManager::class)->name())->toBe('broadcast')
        ->and(app(Transport::class))->toBeInstanceOf(LaravelBroadcast::class);
});

it('treats a log broadcaster as no broadcaster', function (): void {
    config()->set('broadcasting.default', 'log');
    config()->set('broadcasting.connections.log', ['driver' => 'log']);

    expect(app(TransportManager::class)->name())->toBe('polling');
});

it('treats a connection named but never defined as no broadcaster', function (): void {
    config()->set('broadcasting.default', 'ghost');
    config()->set('broadcasting.connections', []);

    expect(app(TransportManager::class)->name())->toBe('polling');
});

it('lets an explicit driver override the sniff', function (): void {
    config()->set('filum.transport.driver', 'broadcast');
    expect(app(TransportManager::class)->name())->toBe('broadcast');

    $this->withBroadcaster();
    config()->set('filum.transport.driver', 'polling');
    expect(app(TransportManager::class)->name())->toBe('polling');
});

it('describes a fast poll under polling and a slow one under broadcasting', function (): void {
    config()->set('filum.transport.poll_interval', 5);
    config()->set('filum.transport.reconcile_interval', 30);

    expect(app(Polling::class)->descriptor())->toBe(['driver' => 'polling', 'poll' => 5]);

    $this->withBroadcaster();

    expect(app(LaravelBroadcast::class)->descriptor())->toBe(['driver' => 'broadcast', 'poll' => 30]);
});

it('falls back to sane intervals when config holds nonsense', function (): void {
    config()->set('filum.transport.poll_interval', 'soon');
    config()->set('filum.transport.reconcile_interval', -1);

    expect(app(Polling::class)->descriptor()['poll'])->toBe(5)
        ->and(app(LaravelBroadcast::class)->descriptor()['poll'])->toBe(30);
});

it('announces nothing under polling', function (): void {
    Event::fake([MessageSent::class, PresenceChanged::class]);

    $message = new Message(['conversation_id' => 1, 'sender_id' => 1, 'body' => 'hi']);

    app(Polling::class)->messageSent($message);
    app(Polling::class)->presenceChanged(1, true);

    Event::assertNotDispatched(MessageSent::class);
    Event::assertNotDispatched(PresenceChanged::class);
});

it('dispatches events under broadcasting', function (): void {
    $this->withBroadcaster();
    Event::fake([MessageSent::class, PresenceChanged::class]);

    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');
    $conversation = app(Conversations::class)->between($nino->id, $giorgi->id);

    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $nino->id,
        'body' => 'გამარჯობა',
    ]);

    app(LaravelBroadcast::class)->messageSent($message);
    app(LaravelBroadcast::class)->presenceChanged($nino->id, true);

    Event::assertDispatched(MessageSent::class);
    Event::assertDispatched(PresenceChanged::class);
});

it('swallows and logs a broadcaster failure instead of losing the message', function (): void {
    Log::spy();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('reverb is down'));

    $transport = new LaravelBroadcast($dispatcher, config(), app(LoggerInterface::class));

    $message = new Message(['conversation_id' => 1, 'sender_id' => 1, 'body' => 'hi']);

    $transport->messageSent($message);
    $transport->presenceChanged(1, false);

    Log::shouldHaveReceived('warning')->twice();
});
