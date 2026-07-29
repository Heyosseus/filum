<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|string $sender_id
 * @property string $body
 * @property \Carbon\CarbonInterface $created_at
 */
final class Message extends Model
{
    protected $table = 'filum_messages';

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'sender_id', 'body'];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
