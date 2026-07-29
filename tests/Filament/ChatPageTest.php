<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Filum;
use Heyosseus\Filum\FilumPlugin;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Pages\Chat;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->actingAs($this->nino);
});

it('renders the chat page', function (): void {
    Livewire::test(Chat::class)
        ->assertOk()
        ->assertSee(__('filum::filum.page.heading'));
});

it('names itself in the panel navigation', function (): void {
    expect(Chat::getNavigationLabel())->toBe(__('filum::filum.nav.chat'))
        ->and(Chat::getNavigationIcon())->toBe('heroicon-o-chat-bubble-left-right');
});

it('titles and heads itself from the translations', function (): void {
    $page = Livewire::test(Chat::class)->instance();

    expect($page->getTitle())->toBe(__('filum::filum.page.title'))
        ->and($page->getHeading())->toBe(__('filum::filum.page.heading'))
        ->and($page->getView())->toBe('filum::pages.chat');
});

it('titles itself in Georgian when the panel is', function (): void {
    app()->setLocale('ka');

    expect(Livewire::test(Chat::class)->instance()->getTitle())->toBe('ჩატი');
});

it('shows no badge when everything is read', function (): void {
    expect(Chat::getNavigationBadge())->toBeNull();
});

it('badges the navigation with unread messages', function (): void {
    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    app(Messages::class)->send($conversation, $this->giorgi, 'knock knock');

    expect(Chat::getNavigationBadge())->toBe('1');
});

it('has no badge for nobody in particular', function (): void {
    auth()->logout();

    expect(Chat::getNavigationBadge())->toBeNull();
});

it('takes its sort and group from config', function (): void {
    expect(Chat::getNavigationSort())->toBeNull()
        ->and(Chat::getNavigationGroup())->toBeNull();

    config()->set('filum.navigation.sort', 3);
    config()->set('filum.navigation.group', 'Team');

    expect(Chat::getNavigationSort())->toBe(3)
        ->and(Chat::getNavigationGroup())->toBe('Team');
});

it('is closed to anyone the gate refuses', function (): void {
    expect(Chat::canAccess())->toBeTrue()
        ->and(Chat::shouldRegisterNavigation())->toBeTrue();

    Filum::auth(static fn (): bool => false);

    expect(Chat::canAccess())->toBeFalse()
        ->and(Chat::shouldRegisterNavigation())->toBeFalse();
});

it('registers the page on the panel', function (): void {
    $panel = filament()->getPanel('filum-test');

    expect($panel->getPages())->toContain(Chat::class);
});

it('identifies itself as a plugin', function (): void {
    expect(FilumPlugin::make()->getId())->toBe('filum');
});

it('registers nothing when the master switch is off', function (): void {
    config()->set('filum.enabled', false);

    $panel = filament()->getPanel('filum-test');
    $plugin = FilumPlugin::make();

    // A disabled Filum is absent rather than present-and-declining.
    $plugin->register($panel);
    $plugin->boot($panel);

    expect(Filum::enabled())->toBeFalse();
});
