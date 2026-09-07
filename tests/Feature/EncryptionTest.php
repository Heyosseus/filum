<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Support\EncryptedBody;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
});

/**
 * What is actually in the column, with no cast in the way.
 */
function stored(int $id): string
{
    return (string) DB::table('filum_messages')->where('id', $id)->value('body');
}

it('writes a message body as ciphertext', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'the safe code is 4417');

    $column = stored($message->id);

    expect($column)->not->toBe('the safe code is 4417')
        ->and($column)->not->toContain('4417')
        ->and(EncryptedBody::isCipherText($column))->toBeTrue();
});

it('reads back exactly what was written, Georgian included', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'გამარჯობა, როგორ ხარ?');

    expect(Message::query()->findOrFail($message->id)->body)->toBe('გამარჯობა, როგორ ხარ?');
});

it('hides a quoted reply as thoroughly as the message it answers', function (): void {
    $asked = app(Messages::class)->send($this->conversation, $this->nino, 'where is the manifest?');
    $answered = app(Messages::class)->send($this->conversation, $this->giorgi, 'on your desk', $asked->id);

    expect(stored($asked->id))->not->toContain('manifest')
        ->and(stored($answered->id))->not->toContain('desk')
        ->and(Message::query()->with('replyTo')->findOrFail($answered->id)->replyTo?->body)
        ->toBe('where is the manifest?');
});

it('leaves an attachment-only message empty rather than storing a ciphertext of nothing', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, '', null, [
        ['disk' => 'local', 'path' => 'filum/1/x.pdf', 'name' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 10],
    ]);

    expect(stored($message->id))->toBe('')
        ->and(Message::query()->findOrFail($message->id)->body)->toBe('');
});

it('writes plaintext when encryption is switched off', function (): void {
    config()->set('filum.messages.encrypt', false);

    $message = app(Messages::class)->send($this->conversation, $this->nino, 'nothing secret');

    expect(stored($message->id))->toBe('nothing secret');
});

it('still reads an encrypted archive after encryption is switched off', function (): void {
    // Otherwise the switch would be a way to lose everything written while it was
    // on, which is not a switch anybody should be offered.
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'written while it was on');

    config()->set('filum.messages.encrypt', false);

    expect(Message::query()->findOrFail($message->id)->body)->toBe('written while it was on');
});

it('shows a plaintext row written before encryption was switched on', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'placeholder');

    DB::table('filum_messages')->where('id', $message->id)->update(['body' => 'from the old days']);

    expect(Message::query()->findOrFail($message->id)->body)->toBe('from the old days');
});

it('costs one line rather than the whole thread when a body will not open', function (): void {
    Log::spy();

    $message = app(Messages::class)->send($this->conversation, $this->nino, 'readable');

    // A payload from a different key: a rotated APP_KEY, or a row copied in from
    // another application.
    $foreign = new Illuminate\Encryption\Encrypter(random_bytes(32), 'aes-256-cbc');
    DB::table('filum_messages')->where('id', $message->id)->update([
        'body' => $foreign->encryptString('unreachable'),
    ]);

    expect(Message::query()->findOrFail($message->id)->body)
        ->toBe(__('filum::filum.conversation.unreadable'));

    Log::shouldHaveReceived('warning')->once();
});

it('does not mistake ordinary text for a payload', function (): void {
    expect(EncryptedBody::isCipherText('the invoice is on your desk'))->toBeFalse()
        ->and(EncryptedBody::isCipherText(base64_encode('not json at all')))->toBeFalse()
        ->and(EncryptedBody::isCipherText(base64_encode('{"iv":"a"}')))->toBeFalse()
        ->and(EncryptedBody::isCipherText(app(Encrypter::class)->encryptString('x')))->toBeTrue();
});

it('reads a missing body as an empty one', function (): void {
    $cast = new EncryptedBody;

    expect($cast->get(new Message, 'body', null, []))->toBe('')
        ->and($cast->set(new Message, 'body', null, []))->toBe(['body' => '']);
});
