<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|string $user_id
 * @property string $state
 * @property int|string|null $invited_by_id
 * @property \Carbon\CarbonInterface|null $joined_at
 * @property int|null $last_read_message_id
 */
final class Participant extends Model
{
    protected $table = 'filum_participants';

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'user_id', 'state', 'invited_by_id', 'joined_at', 'last_read_message_id'];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_read_message_id' => 'integer',
        ];
    }
}
