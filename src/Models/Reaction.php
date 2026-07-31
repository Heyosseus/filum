<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $message_id
 * @property int|string $user_id
 * @property string $emoji
 */
final class Reaction extends Model
{
    protected $table = 'filum_reactions';

    /** @var list<string> */
    protected $fillable = ['message_id', 'user_id', 'emoji'];

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
