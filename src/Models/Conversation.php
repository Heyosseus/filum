<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property string $kind
 * @property string|null $name
 * @property int|string|null $owner_id
 * @property string|null $key
 * @property \Carbon\CarbonInterface|null $last_message_at
 */
final class Conversation extends Model
{
    protected $table = 'filum_conversations';

    /** @var list<string> */
    protected $fillable = ['kind', 'name', 'owner_id', 'key', 'last_message_at'];

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function isGroup(): bool
    {
        return $this->kind === 'group';
    }

    /**
     * Whether the given user takes part in this conversation.
     *
     * Joined, specifically. This method backs both broadcast channel authorization
     * and the sender check in Messages::send, so narrowing it here is what keeps a
     * pending invitee out of the socket and the send path -- rather than three
     * separate places each remembering to check.
     */
    public function includes(int|string $userId): bool
    {
        return $this->participants()
            ->where('user_id', $userId)
            ->where('state', 'joined')
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }
}
