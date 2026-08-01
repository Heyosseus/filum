<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $message_id
 * @property int|string $user_id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $size
 */
final class Attachment extends Model
{
    protected $table = 'filum_attachments';

    /** @var list<string> */
    protected $fillable = ['message_id', 'user_id', 'disk', 'path', 'name', 'mime', 'size'];

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Whether this is something a browser can show inline rather than download.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /**
     * A size a person can read, rather than a number of bytes.
     */
    public function readableSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $size : number_format($size, 1)).' '.$units[$unit];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
