<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|string $sender_id
 * @property int|null $reply_to_id
 * @property string $body
 * @property \Carbon\CarbonInterface $created_at
 */
final class Message extends Model
{
    protected $table = 'filum_messages';

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'sender_id', 'reply_to_id', 'body'];

    /**
     * The message this one answers, if it answers one.
     *
     * @return BelongsTo<Message, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
