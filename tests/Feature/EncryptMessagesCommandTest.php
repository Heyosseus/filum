<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Support\EncryptedBody;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
});

/**
 * A message as an install that predates encryption would have it.
 */
function plaintext(Heyosseus\Filum\Models\Conversation $conversation, mixed $sender, string $body): Message
{
    $message = app(Messages::class)->send($conversation, $sender, 'placeholder');

    DB::table('filum_messages')->where('id', $message->id)->update(['body' => $body]);

    return $message;
}

it('converts an existing plaintext archive', function (): void {
    $old = plaintext($this->conversation, $this->nino, 'the safe code is 4417');

    $this->artisan('filum:encrypt-messages')->assertSuccessful();

    $column = (string) DB::table('filum_messages')->where('id', $old->id)->value('body');

    expect($column)->not->toContain('4417')
        ->and(EncryptedBody::isCipherText($column))->toBeTrue()
        ->and(Message::query()->findOrFail($old->id)->body)->toBe('the safe code is 4417');
});

it('leaves what is already encrypted exactly as it was', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'already done');
    $before = (string) DB::table('filum_messages')->where('id', $message->id)->value('body');

    $this->artisan('filum:encrypt-messages')->assertSuccessful();

    expect(DB::table('filum_messages')->where('id', $message->id)->value('body'))->toBe($before);
});

it('is safe to run twice', function (): void {
    plaintext($this->conversation, $this->nino, 'run me twice');

    $this->artisan('filum:encrypt-messages')->assertSuccessful();
    $this->artisan('filum:encrypt-messages')->assertSuccessful();

    expect(wrote('run me twice'))->toBeTrue();
});

it('walks the archive a chunk at a time', function (): void {
    foreach (range(1, 5) as $n) {
        plaintext($this->conversation, $this->nino, "old message {$n}");
    }

    $this->artisan('filum:encrypt-messages', ['--chunk' => 2])->assertSuccessful();

    expect(wrote('old message 1'))->toBeTrue()
        ->and(wrote('old message 5'))->toBeTrue();
});

it('falls back to a sensible chunk when given a nonsense one', function (): void {
    plaintext($this->conversation, $this->nino, 'still converted');

    $this->artisan('filum:encrypt-messages', ['--chunk' => 0])->assertSuccessful();

    expect(wrote('still converted'))->toBeTrue();
});

it('leaves an attachment-only message alone', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, '', null, [
        ['disk' => 'local', 'path' => 'filum/1/x.pdf', 'name' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 10],
    ]);

    $this->artisan('filum:encrypt-messages')->assertSuccessful();

    expect(DB::table('filum_messages')->where('id', $message->id)->value('body'))->toBe('');
});

it('refuses to run while encryption is switched off', function (): void {
    config()->set('filum.messages.encrypt', false);

    $message = app(Messages::class)->send($this->conversation, $this->nino, 'left alone');

    $this->artisan('filum:encrypt-messages')->assertFailed();

    // Converting here would encrypt an archive the configuration says is meant to
    // be readable, and nothing would ever convert it back.
    expect(DB::table('filum_messages')->where('id', $message->id)->value('body'))->toBe('left alone');
});
