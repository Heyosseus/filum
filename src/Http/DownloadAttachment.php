<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Http;

use Heyosseus\Filum\Attachments\Attachments;
use Heyosseus\Filum\Filum;
use Heyosseus\Filum\Models\Attachment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an attachment, to the people the conversation admits and nobody else.
 *
 * Registered as a panel route rather than a bare web one, which is what makes
 * the authorization trustworthy: inside the panel, Filum resolves the panel's own
 * guard, so "who is asking" has the same answer here as it does in the chat.
 */
final readonly class DownloadAttachment
{
    public function __construct(private Attachments $attachments) {}

    public function __invoke(int $attachment): Response|StreamedResponse
    {
        $user = Filum::user();
        // The message and its conversation are what authorization turns on, so
        // they are asked for by name rather than reached for afterwards -- an
        // application running preventLazyLoading would refuse the reach.
        $file = Attachment::query()->with('message.conversation')->find($attachment);

        if (! Filum::authorized($user) || ! $user instanceof Authenticatable || ! $file instanceof Attachment) {
            abort(404);
        }

        // 404 rather than 403 throughout: whether a given file exists is itself
        // something only a participant should learn.
        if (! $this->attachments->readable($file, $user)) {
            abort(404);
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            abort(404);
        }

        // Images inline so a screenshot can be looked at without a round trip
        // through the downloads folder; everything else as an attachment.
        return $file->isImage()
            ? $disk->response($file->path, $file->name)
            : $disk->download($file->path, $file->name);
    }
}
