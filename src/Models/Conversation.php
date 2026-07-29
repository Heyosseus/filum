<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property string|null $key
 * @property \Carbon\CarbonInterface|null $last_message_at
 */
final class Conversation extends Model
{
    protected $table = 'filum_conversations';

    /** @var list<string> */
    protected $fillable = ['key', 'last_message_at'];

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

    /**
     * Whether the given user takes part in this conversation.
     */
    public function includes(int|string $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
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
