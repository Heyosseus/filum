<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentView;
use Heyosseus\Filum\Filum;
use Heyosseus\Filum\Support\Compat;

it('hangs the overlay on every panel page', function (): void {
    $this->actingAs($this->user('Nino'));

    $rendered = FilamentView::renderHook(Compat::bodyEndHook())->toHtml();

    expect($rendered)->toContain('filum-root')
        ->and($rendered)->toContain('filum-mode-overlay');
});

it('renders nothing at all for someone the gate refuses', function (): void {
    $this->actingAs($this->user('Nino'));

    Filum::auth(static fn (): bool => false);

    $rendered = FilamentView::renderHook(Compat::bodyEndHook())->toHtml();

    // Not a chat that turns you away -- no chat.
    expect($rendered)->not->toContain('filum-root');
});

it('renders nothing for a guest', function (): void {
    $rendered = FilamentView::renderHook(Compat::bodyEndHook())->toHtml();

    expect($rendered)->not->toContain('filum-root');
});
