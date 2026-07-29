<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Events\PresenceChanged;
use Heyosseus\Filum\Presence\Heartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('records a heartbeat and reads it back', function (): void {
    $nino = $this->user('Nino');

    $store = app(PresenceStore::class);
    $store->beat($nino->id);

    expect($store->active())->toBe([$nino->id])
        ->and($store->seenAt($nino->id))->not->toBeNull();
});

it('keeps one row per user however often they beat', function (): void {
    $nino = $this->user('Nino');

    $store = app(PresenceStore::class);
    $store->beat($nino->id);
    $store->beat($nino->id);
    $store->beat($nino->id);

    expect(DB::table('filum_presence')->count())->toBe(1);
});

it('drops a user who has not beaten inside the ttl', function (): void {
    config()->set('filum.presence.ttl', 180);

    $nino = $this->user('Nino');
    $store = app(PresenceStore::class);

    CarbonImmutable::setTestNow(CarbonImmutable::now()->subMinutes(10));
    $store->beat($nino->id);
    CarbonImmutable::setTestNow();

    expect($store->active())->toBe([]);

    CarbonImmutable::setTestNow();
});

it('keeps a user who missed a single beat', function (): void {
    config()->set('filum.presence.heartbeat_interval', 60);
    config()->set('filum.presence.ttl', 180);

    $nino = $this->user('Nino');
    $store = app(PresenceStore::class);

    CarbonImmutable::setTestNow(CarbonImmutable::now()->subSeconds(90));
    $store->beat($nino->id);
    CarbonImmutable::setTestNow();

    expect($store->active())->toBe([$nino->id]);

    CarbonImmutable::setTestNow();
});

it('reports nothing for a user never seen', function (): void {
    expect(app(PresenceStore::class)->seenAt(999))->toBeNull();
});

it('announces only the moment a user arrives, not every beat', function (): void {
    $this->withBroadcaster();
    Event::fake([PresenceChanged::class]);

    $nino = $this->user('Nino');
    $heartbeat = app(Heartbeat::class);

    $heartbeat->beat($nino);
    $heartbeat->beat($nino);
    $heartbeat->beat($nino);

    Event::assertDispatchedTimes(PresenceChanged::class, 1);
});

it('writes presence even with no broadcaster at all', function (): void {
    $nino = $this->user('Nino');

    app(Heartbeat::class)->beat($nino);

    expect(app(PresenceStore::class)->active())->toBe([$nino->id]);
});
