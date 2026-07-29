<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Pages;

use Filament\Pages\Page;
use Heyosseus\Filum\Filum;
use Heyosseus\Filum\Messages\Messages;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Chat's home in the panel.
 *
 * The view is supplied through getView() rather than the $view property: it is
 * static in Filament 4 and an instance property in Filament 5, so declaring it
 * here would be fatal on one of the two. The accessor exists in both.
 */
final class Chat extends Page
{
    public function getView(): string
    {
        return 'filum::pages.chat';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function getNavigationLabel(): string
    {
        return __('filum::filum.nav.chat');
    }

    public function getTitle(): string
    {
        return __('filum::filum.page.title');
    }

    public function getHeading(): string
    {
        return __('filum::filum.page.heading');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filum.navigation.sort');

        return is_int($sort) ? $sort : null;
    }

    public static function getNavigationGroup(): ?string
    {
        $group = config('filum.navigation.group');

        return is_string($group) && $group !== '' ? $group : null;
    }

    /**
     * The unread count, or nothing at all when there is nothing to read.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = Filum::user();

        if (! $user instanceof Authenticatable) {
            return null;
        }

        $unread = app(Messages::class)->unreadTotal($user);

        return $unread > 0 ? (string) $unread : null;
    }

    /**
     * One gate governs every surface, so the page is simply not there for
     * someone the chat would refuse.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return Filum::authorized();
    }

    public static function canAccess(): bool
    {
        return Filum::authorized();
    }
}
