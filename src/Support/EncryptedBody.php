<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;

/**
 * A message body, ciphertext in the database and words everywhere else.
 *
 * Encryption happens here rather than in the send path because a body reaches
 * the column by more than one road -- the sender, a seeder, an application's own
 * Message::create -- and a rule enforced at one of them is not a rule. The cast
 * is the column's own behaviour, so every road obeys it.
 *
 * Reading is deliberately more forgiving than writing. An install that had rows
 * before Filum encrypted anything must keep showing them, so a value that is not
 * a Laravel payload is handed back as it was found; filum:encrypt-messages turns
 * those into ciphertext when the operator is ready. And a value that *is* a
 * payload but will not open -- a rotated APP_KEY, a row copied between
 * applications -- costs one unreadable line and a warning in the log, not a
 * five-hundred on a chat panel whose other four hundred messages are fine.
 *
 * @implements CastsAttributes<string, string>
 */
final class EncryptedBody implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        if (! self::isCipherText($value)) {
            return $value;
        }

        try {
            return self::encrypter()->decryptString($value);
        } catch (DecryptException $e) {
            $this->logger()->warning('Filum could not decrypt a message body.', [
                'message_id' => $model->getKey(),
                'exception' => $e,
            ]);

            return (string) __('filum::filum.conversation.unreadable');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $body = is_scalar($value) ? (string) $value : '';

        // An attachment-only message has no words to hide, and a ciphertext of
        // nothing would only make the column harder to read in a shell.
        if ($body === '' || ! self::enabled()) {
            return [$key => $body];
        }

        return [$key => self::encrypt($body)];
    }

    /**
     * Whether new bodies are written as ciphertext.
     *
     * Reading never asks: turning the switch off must leave what is already
     * encrypted readable, or the switch would be a way to lose the archive.
     */
    public static function enabled(): bool
    {
        return app(Repository::class)->get('filum.messages.encrypt', true) === true;
    }

    public static function encrypt(string $body): string
    {
        return self::encrypter()->encryptString($body);
    }

    /**
     * Whether a stored value is one of ours.
     *
     * Laravel's payload is base64 over JSON carrying an iv, a value and a mac, so
     * the shape is the test. Human text does not accidentally base64-decode into
     * that object, which is what lets a plaintext archive and an encrypted one
     * live in the same column while the archive is converted.
     */
    public static function isCipherText(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && array_key_exists('iv', $payload)
            && array_key_exists('value', $payload)
            && array_key_exists('mac', $payload);
    }

    private static function encrypter(): StringEncrypter
    {
        return app(StringEncrypter::class);
    }

    private function logger(): LoggerInterface
    {
        return app(LoggerInterface::class);
    }
}
