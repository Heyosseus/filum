<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Conversations;

use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Participant;
use Illuminate\Database\QueryException;
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

        $existing = Conversation::query()->where('key', $key)->first();

        if ($existing instanceof Conversation) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($key, $a, $b): Conversation {
                $conversation = Conversation::query()->create(['key' => $key]);

                foreach ([$a, $b] as $userId) {
                    Participant::query()->create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                    ]);
                }

                return $conversation;
            });
        } catch (QueryException) {
            // Lost the race. The winner's row is the one we both wanted.
            return Conversation::query()->where('key', $key)->firstOrFail();
        }
    }

    /**
     * The participant row joining a user to a conversation, if there is one.
     */
    public function participant(Conversation $conversation, int|string $userId): ?Participant
    {
        return Participant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->first();
    }
}
