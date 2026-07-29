<?php

declare(strict_types=1);

use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Filum;
use Heyosseus\Filum\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

/*
| Both callbacks defer to the same Filum::auth gate the UI uses, so nobody can
| subscribe to something the page would not have shown them.
*/

Broadcast::channel('filum.conversation.{conversation}', static function (Authenticatable $user, int $conversation): bool {
    if (! Filum::authorized($user)) {
        return false;
    }

    $model = Conversation::query()->find($conversation);

    if (! $model instanceof Conversation) {
        return false;
    }

    return $model->includes(app(UserProvider::class)->id($user));
});

Broadcast::channel('filum.presence', static fn (Authenticatable $user): bool => Filum::authorized($user));
