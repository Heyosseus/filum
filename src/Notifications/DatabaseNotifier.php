<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Heyosseus\Filum\Contracts\Notifier;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Pages\Chat;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Notifications\Notifiable;
use Throwable;

/**
 * Rings Filament's own notification bell.
 *
 * Filum writes into the application's existing notifications table rather than
 * shipping one of its own, so an unread message appears in the same bell as
 * everything else the panel already tells people about -- and needs no
 * broadcaster to get there, because Filament polls the bell.
 *
 * Nothing here catches: the send path guards every notifier, custom ones
 * included, so swallowing failures a second time here would only hide them.
 */
final class DatabaseNotifier implements Notifier
{
    /**
     * Memoised for the request. Filament's database notifications are opt-in, so
     * the table may legitimately not exist; asking once is cheaper than a caught
     * query exception per message, and quieter in the log.
     */
    private ?bool $hasTable = null;

    public function __construct(
        private readonly UserProvider $users,
        private readonly Builder $schema,
    ) {}

    public function messageSent(Message $message, Authenticatable $recipient): void
    {
        if (! $this->canNotify($recipient)) {
            return;
        }

        $sender = $this->users->find((string) $message->sender_id);

        $this->ring(
            $sender instanceof Authenticatable ? $this->users->name($sender) : '',
            $this->excerpt($message->body),
            'heroicon-o-chat-bubble-left-ellipsis',
            $recipient,
        );
    }

    public function invited(Conversation $group, Authenticatable $recipient, Authenticatable $inviter): void
    {
        if (! $this->canNotify($recipient)) {
            return;
        }

        $this->ring(
            $this->users->name($inviter),
            __('filum::filum.notification.invited_body', ['group' => (string) $group->name]),
            'heroicon-o-user-plus',
            $recipient,
        );
    }

    private function ring(string $title, string $body, string $icon, Authenticatable $recipient): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->actions($this->actions())
            ->sendToDatabase($recipient);
    }

    /**
     * Whether this recipient can be written to at all.
     *
     * The user model is the application's, so it need not be notifiable, and the
     * notifications table is the application's, so it need not be migrated.
     * Neither is a misconfiguration worth an exception -- both simply mean the
     * bell is not available here.
     */
    private function canNotify(Authenticatable $recipient): bool
    {
        if (! $recipient instanceof Model || ! in_array(Notifiable::class, class_uses_recursive($recipient), true)) {
            return false;
        }

        return $this->hasTable ??= $this->schema->hasTable('notifications');
    }

    /**
     * A one-line preview, because the bell is not the place to read a paragraph.
     */
    private function excerpt(string $body): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $body);
        $body = trim(is_string($collapsed) ? $collapsed : $body);

        return mb_strlen($body) > 120 ? mb_substr($body, 0, 119).'…' : $body;
    }

    /**
     * A link to the chat, when one can be worked out.
     *
     * Page URLs are resolved against the panel serving the request, which is not
     * guaranteed to exist -- a message sent from a command or a queued job has no
     * panel. An informational notification beats a broken link.
     *
     * @return array<int, Action>
     */
    private function actions(): array
    {
        try {
            $url = Chat::getUrl();
        } catch (Throwable) {
            return [];
        }

        return [
            Action::make('filum-open')
                ->label(__('filum::filum.notification.open'))
                ->url($url)
                ->markAsRead(),
        ];
    }
}
