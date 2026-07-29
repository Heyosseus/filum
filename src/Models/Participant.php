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
 * @property int|null $last_read_message_id
 */
final class Participant extends Model
{
    protected $table = 'filum_participants';

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id'];

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
            'last_read_message_id' => 'integer',
        ];
    }
}
