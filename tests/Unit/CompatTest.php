<?php

declare(strict_types=1);

use Heyosseus\Filum\Support\Compat;

it('reports the installed Filament major', function (): void {
    expect(Compat::filamentMajor())->toBeIn([4, 5]);
});

it('reports the installed Livewire major', function (): void {
    expect(Compat::livewireMajor())->toBeIn([3, 4]);
});

it('pairs each Filament major with the Livewire it carries', function (): void {
    // Filament 4 requires livewire ^3.5, Filament 5 requires ^4.1. If this ever
    // fails, the Compat seam is reasoning about a combination it has not been
    // told about.
    expect(Compat::livewireMajor())->toBe(Compat::filamentMajor() - 1);
});

it('reads a major out of a version string', function (): void {
    expect(Compat::majorFrom('5.7.4', 9))->toBe(5)
        ->and(Compat::majorFrom('v4.12.4', 9))->toBe(4)
        ->and(Compat::majorFrom('3.5.0-beta.2', 9))->toBe(3);
});

it('falls back when a version names no major at all', function (): void {
    // A branch install reports something like this, and it names no major.
    expect(Compat::majorFrom('dev-main', 5))->toBe(5)
        ->and(Compat::majorFrom('', 4))->toBe(4)
        ->and(Compat::majorFrom(null, 7))->toBe(7);
});

it('resolves the body-end render hook through the class constant', function (): void {
    expect(Compat::bodyEndHook())
        ->toBeString()
        ->toBe(constant('Filament\View\PanelsRenderHook::BODY_END'));
});
