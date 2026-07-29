<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentView;
use Heyosseus\Filum\Support\Compat;

it('hangs no overlay on the panel', function (): void {
    $this->actingAs($this->user('Nino'));

    $rendered = FilamentView::renderHook(Compat::bodyEndHook())->toHtml();

    expect($rendered)->not->toContain('filum-root');
});

it('keeps the page, which is the feature', function (): void {
    $this->actingAs($this->user('Nino'));

    expect(filament()->getPanel('filum-test')->getPages())
        ->toContain(Heyosseus\Filum\Pages\Chat::class);
});
