<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Attachments;

use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Exceptions\AttachmentRefused;
use Heyosseus\Filum\Models\Attachment;
use Heyosseus\Filum\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;

/**
 * Files carried by a message.
 *
 * They are stored on a private disk and served through the panel, never linked
 * to directly. A public URL would make every document somebody sends readable by
 * anyone who guessed the path, which for a back office is the whole of its
 * paperwork.
 */
final readonly class Attachments
{
    /** @var list<string> */
    private const array FALLBACK_MIMES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'application/pdf', 'text/plain', 'text/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private UserProvider $users,
        private Repository $config,
    ) {}

    /**
     * Check a set of uploads and put them away, ready to hang off a message.
     *
     * Everything is validated before anything is written, so a refusal on the
     * third file does not leave the first two orphaned on the disk.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{disk: string, path: string, name: string, mime: string, size: int}>
     *
     * @throws AttachmentRefused
     */
    public function accept(Conversation $conversation, array $files): array
    {
        if ($files === []) {
            return [];
        }

        if (! $this->enabled()) {
            throw AttachmentRefused::disabled();
        }

        $max = $this->perMessage();

        if (count($files) > $max) {
            throw AttachmentRefused::tooMany($max);
        }

        foreach ($files as $file) {
            $this->check($file);
        }

        $disk = $this->disk();
        $stored = [];

        foreach ($files as $file) {
            $stored[] = [
                'disk' => $disk,
                // Foldered by conversation so a deleted conversation's files are
                // findable, and named by Laravel so nothing a person typed ever
                // becomes a path.
                'path' => (string) $file->store('filum/'.$conversation->id, $disk),
                'name' => $this->safeName($file),
                'mime' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * Whether this person may fetch this file.
     *
     * Membership of the conversation the file hangs on, which is the same
     * question the thread itself answers -- a file is not more public than the
     * message that carried it.
     */
    public function readable(Attachment $attachment, Authenticatable $user): bool
    {
        $conversation = $attachment->message?->conversation;

        return $conversation instanceof Conversation
            && $conversation->includes($this->users->id($user));
    }

    public function enabled(): bool
    {
        return $this->config->get('filum.attachments.enabled', true) === true;
    }

    public function disk(): string
    {
        $disk = $this->config->get('filum.attachments.disk', 'local');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    /**
     * The largest file accepted, in kilobytes.
     */
    public function maxKilobytes(): int
    {
        $max = $this->config->get('filum.attachments.max_size', 10240);

        return is_int($max) && $max > 0 ? $max : 10240;
    }

    public function perMessage(): int
    {
        $max = $this->config->get('filum.attachments.max_per_message', 4);

        return is_int($max) && $max > 0 ? $max : 4;
    }

    /**
     * @return list<string>
     */
    public function mimes(): array
    {
        $mimes = $this->config->get('filum.attachments.mimes');

        if (! is_array($mimes) || $mimes === []) {
            return self::FALLBACK_MIMES;
        }

        return array_values(array_filter($mimes, is_string(...)));
    }

    /**
     * @throws AttachmentRefused
     */
    private function check(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxKilobytes() * 1024) {
            throw AttachmentRefused::tooLarge($this->safeName($file), $this->maxKilobytes());
        }

        // The type is read from the file's own bytes rather than from what the
        // browser claimed, so renaming a script to .png does not get it past here.
        if (! in_array((string) $file->getMimeType(), $this->mimes(), true)) {
            throw AttachmentRefused::wrongType($this->safeName($file));
        }
    }

    /**
     * The name to show, stripped of anything that is not a name.
     */
    private function safeName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());

        return mb_substr($name === '' ? 'file' : $name, 0, 120);
    }
}
