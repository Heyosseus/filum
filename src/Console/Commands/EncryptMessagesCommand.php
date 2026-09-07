<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Console\Commands;

use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Support\EncryptedBody;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Turn an existing plaintext archive into ciphertext.
 *
 * Filum encrypts what it writes, but it will not rewrite an install's history
 * behind its back: switching encryption on leaves older rows readable and this
 * command converts them, so the moment the archive is rewritten is a moment the
 * operator chose and could take a backup before.
 *
 * Safe to run twice. A row that is already ciphertext is left exactly as it is,
 * which also means an interrupted run is resumed simply by running it again.
 */
final class EncryptMessagesCommand extends Command
{
    protected $signature = 'filum:encrypt-messages {--chunk=200 : How many messages to read at a time}';

    protected $description = 'Encrypt message bodies written before encryption was switched on.';

    public function handle(): int
    {
        if (! EncryptedBody::enabled()) {
            $this->components->error(__('filum::filum.encrypt.disabled'));

            return self::FAILURE;
        }

        $chunk = (int) $this->option('chunk');
        $chunk = $chunk > 0 ? $chunk : 200;

        $converted = 0;

        // Written straight to the column rather than through save(): the cast has
        // already done the only interesting part, and going through the model
        // would stamp updated_at on an archive nobody edited and fire model events
        // at an application that thinks these messages arrived years ago.
        Message::query()
            ->orderBy('id')
            ->chunkById($chunk, function (Collection $messages) use (&$converted): void {
                foreach ($messages as $message) {
                    $cipher = $this->cipherFor($message);

                    if ($cipher === null) {
                        continue;
                    }

                    Message::query()->whereKey($message->getKey())->toBase()->update(['body' => $cipher]);

                    $converted++;
                }
            });

        $this->components->info(__('filum::filum.encrypt.done', ['count' => $converted]));

        return self::SUCCESS;
    }

    /**
     * The ciphertext this message should be stored as, or null to leave it be.
     *
     * Null covers the two rows there is nothing to do to: one that is already
     * ciphertext, and an attachment-only message whose body is empty and has no
     * words to hide.
     */
    private function cipherFor(Message $message): ?string
    {
        $stored = $message->getRawOriginal('body');

        if (! is_string($stored) || $stored === '' || EncryptedBody::isCipherText($stored)) {
            return null;
        }

        return EncryptedBody::encrypt($stored);
    }
}
