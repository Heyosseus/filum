<?php

declare(strict_types=1);

use Heyosseus\Filum\Attachments\Attachments;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Exceptions\AttachmentRefused;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    $this->attachments = app(Attachments::class);
});

it('stores a file and describes it for the message to carry', function (): void {
    $stored = $this->attachments->accept($this->conversation, [
        UploadedFile::fake()->image('manifest.png'),
    ]);

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['name'])->toBe('manifest.png')
        ->and($stored[0]['mime'])->toBe('image/png')
        ->and($stored[0]['disk'])->toBe('local');

    // Foldered by conversation, and never named after anything a person typed.
    expect($stored[0]['path'])->toStartWith('filum/'.$this->conversation->id.'/')
        ->and($stored[0]['path'])->not->toContain('manifest');

    Storage::disk('local')->assertExists($stored[0]['path']);
});

it('accepts nothing without touching the disk', function (): void {
    expect($this->attachments->accept($this->conversation, []))->toBe([]);
});

it('refuses a file larger than the configured maximum', function (): void {
    config()->set('filum.attachments.max_size', 10);

    $this->attachments->accept($this->conversation, [
        UploadedFile::fake()->create('huge.pdf', 40, 'application/pdf'),
    ]);
})->throws(AttachmentRefused::class);

it('refuses a kind of file the application does not accept', function (): void {
    $this->attachments->accept($this->conversation, [
        UploadedFile::fake()->create('payload.php', 1, 'application/x-php'),
    ]);
})->throws(AttachmentRefused::class);

it('refuses more files than a message may carry', function (): void {
    config()->set('filum.attachments.max_per_message', 2);

    $this->attachments->accept($this->conversation, [
        UploadedFile::fake()->image('a.png'),
        UploadedFile::fake()->image('b.png'),
        UploadedFile::fake()->image('c.png'),
    ]);
})->throws(AttachmentRefused::class);

it('refuses everything when attachments are switched off', function (): void {
    config()->set('filum.attachments.enabled', false);

    $this->attachments->accept($this->conversation, [UploadedFile::fake()->image('a.png')]);
})->throws(AttachmentRefused::class);

it('writes nothing at all when one file in a set is refused', function (): void {
    config()->set('filum.attachments.max_size', 10);

    try {
        $this->attachments->accept($this->conversation, [
            UploadedFile::fake()->image('fine.png'),
            UploadedFile::fake()->create('huge.pdf', 40, 'application/pdf'),
        ]);
    } catch (AttachmentRefused) {
        // Everything is checked before anything is written, so a refusal on the
        // second file must not leave the first orphaned on the disk.
    }

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('lets a participant read a file and nobody else', function (): void {
    $message = app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'the manifest',
        null,
        $this->attachments->accept($this->conversation, [UploadedFile::fake()->image('m.png')]),
    );

    $file = Attachment::query()->firstOrFail();

    expect($file->message_id)->toBe($message->id)
        ->and($this->attachments->readable($file, $this->nino))->toBeTrue()
        ->and($this->attachments->readable($file, $this->giorgi))->toBeTrue()
        ->and($this->attachments->readable($file, $this->user('Dato')))->toBeFalse();
});

it('refuses to read a file whose message has gone', function (): void {
    $message = app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'here',
        null,
        $this->attachments->accept($this->conversation, [UploadedFile::fake()->image('m.png')]),
    );

    $file = Attachment::query()->firstOrFail();
    $orphan = $file->replicate();
    $orphan->message_id = $message->id;
    $message->delete();

    expect($this->attachments->readable($orphan, $this->nino))->toBeFalse();
});

it('carries a message with files and no words', function (): void {
    // A file is a message. Requiring words alongside it would mean typing "here"
    // above every document somebody sends.
    $message = app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        '   ',
        null,
        $this->attachments->accept($this->conversation, [UploadedFile::fake()->image('m.png')]),
    );

    expect($message->body)->toBe('')
        ->and($message->attachments()->count())->toBe(1);
});

it('falls back when the configuration is unusable', function (): void {
    config()->set('filum.attachments.disk', '');
    config()->set('filum.attachments.max_size', 0);
    config()->set('filum.attachments.max_per_message', -1);
    config()->set('filum.attachments.mimes', 'not an array');

    expect($this->attachments->disk())->toBe('local')
        ->and($this->attachments->maxKilobytes())->toBe(10240)
        ->and($this->attachments->perMessage())->toBe(4)
        ->and($this->attachments->mimes())->toContain('image/png');
});

it('takes a configured set and drops what is not a string', function (): void {
    config()->set('filum.attachments.mimes', ['image/png', 7]);

    expect($this->attachments->mimes())->toBe(['image/png']);
});

it('reads a size the way a person would', function (): void {
    $file = new Attachment(['size' => 512]);
    expect($file->readableSize())->toBe('512 B');

    $file = new Attachment(['size' => 2048]);
    expect($file->readableSize())->toBe('2.0 KB');

    $file = new Attachment(['size' => 5 * 1024 * 1024]);
    expect($file->readableSize())->toBe('5.0 MB');

    $file = new Attachment(['size' => 3 * 1024 * 1024 * 1024]);
    expect($file->readableSize())->toBe('3.0 GB');
});

it('knows an image from everything else', function (): void {
    expect((new Attachment(['mime' => 'image/png']))->isImage())->toBeTrue()
        ->and((new Attachment(['mime' => 'application/pdf']))->isImage())->toBeFalse();
});

it('reaches the message it hangs on', function (): void {
    app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'here',
        null,
        $this->attachments->accept($this->conversation, [UploadedFile::fake()->image('m.png')]),
    );

    expect(Attachment::query()->firstOrFail()->message->body)->toBe('here');
});
