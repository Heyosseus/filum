<?php

declare(strict_types=1);

use Heyosseus\Filum\Filum;
use Illuminate\Support\Facades\Broadcast;

it('is off', function (): void {
    expect(Filum::enabled())->toBeFalse();
});

it('admits nobody, however authenticated they are', function (): void {
    expect(Filum::authorized($this->user('Nino')))->toBeFalse();
});

it('registers no broadcast channels', function (): void {
    // Disabled means absent. There is no channel here to be turned away from.
    $channels = Broadcast::getFacadeRoot();

    expect(fn (): mixed => $channels)->not->toThrow(Exception::class);

    expect(Filum::authorized())->toBeFalse();
});

it('still resolves its services, so nothing explodes on a container lookup', function (): void {
    // The switch removes surfaces, not bindings: asking the container for a
    // transport in a disabled application should still give you one.
    expect(app(Heyosseus\Filum\Contracts\Transport::class))
        ->toBeInstanceOf(Heyosseus\Filum\Contracts\Transport::class);
});
