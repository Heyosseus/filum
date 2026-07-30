<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Conversations;

use Carbon\CarbonImmutable;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Participant;
use Illuminate\Support\Facades\DB;

final class Conversations
{
    /**
     * The conversation between two people, creating it the first time.
     *
     * The unique key is what makes this safe under concurrency: if two requests
     * both find nothing and both insert, one of them loses at the index and we
     * simply read back the row the winner wrote.
     */
    public function between(int|string $a, int|string $b): Conversation
    {
        $key = ConversationKey::for([$a, $b]);
        $now = CarbonImmutable::now();

        // insertOrIgnore lets the unique index settle the race without an
        // exception path: whoever arrives first creates the row and everyone else
        // quietly does nothing, so two people opening each other at the same
        // moment still end up in one conversation. Catching a duplicate-key error
        // instead would mean re-reading a row that our own rolled-back
        // transaction had just hidden.
        DB::table('filum_conversations')->insertOrIgnore([
            'kind' => 'direct',
            'key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $conversation = Conversation::query()->where('key', $key)->firstOrFail();

        // Idempotent for the same reason, so re-opening a conversation adds
        // nobody twice.
        DB::table('filum_participants')->insertOrIgnore([
            [
                'conversation_id' => $conversation->id,
                'user_id' => $a,
                'state' => 'joined',
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'conversation_id' => $conversation->id,
                'user_id' => $b,
                'state' => 'joined',
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $conversation;
    }

    /**
     * The participant row joining a user to a conversation, if there is one.
     *
     * Joined only. Every caller of this method -- markRead, unreadIn -- already
     * means a full member, and narrowing here closes the dangerous direction:
     * counting unread messages for somebody who cannot open them. unreadTotal
     * queries participants directly rather than through this method, so it
     * filters for itself. Groups reads invited rows through its own methods.
     */
    public function participant(Conversation $conversation, int|string $userId): ?Participant
    {
        return Participant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->where('state', 'joined')
            ->first();
    }
}
