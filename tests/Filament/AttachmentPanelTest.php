<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Livewire\ChatPanel;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Attachment;
use Heyosseus\Filum\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');

    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->actingAs($this->nino, 'panel');

    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    $this->asked = app(Messages::class)->send($this->conversation, $this->giorgi, 'where is the manifest?');
});

it('answers a message from the thread and shows what is being answered', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('reply', $this->asked->id)
        ->assertSet('replyTo', $this->asked->id)
        ->assertSeeHtml('filum-replying')
        ->set('body', 'on your desk')
        ->call('send')
        ->assertSet('replyTo', null);

    expect(Message::query()->where('body', 'on your desk')->value('reply_to_id'))->toBe($this->asked->id);
});

it('drops the answer when it is cancelled', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('reply', $this->asked->id)
        ->call('cancelReply')
        ->assertSet('replyTo', null)
        ->assertDontSeeHtml('filum-replying');
});

it('refuses to answer a message from another conversation', function (): void {
    $elsewhere = app(Conversations::class)->between($this->giorgi->id, $this->user('Dato')->id);
    $theirs = app(Messages::class)->send($elsewhere, $this->giorgi, 'not for you');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('reply', $theirs->id)
        ->assertSet('replyTo', null);
});

it('refuses to answer anything with no conversation open', function (): void {
    Livewire::test(ChatPanel::class)->call('reply', $this->asked->id)->assertSet('replyTo', null);
});

it('forgets an answer whose message has since been deleted', function (): void {
    $panel = Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('reply', $this->asked->id)
        ->assertSeeHtml('filum-replying');

    $this->asked->delete();

    // The reply id is public state and the message it points at can go between
    // picking it and sending, so the strip is re-read rather than trusted.
    $panel->call('tick')->assertDontSeeHtml('filum-replying');
});

it('sends a file with a message', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->set('files', [UploadedFile::fake()->image('manifest.png')])
        ->set('body', 'here it is')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('files', []);

    expect(Attachment::query()->count())->toBe(1)
        ->and(Attachment::query()->value('name'))->toBe('manifest.png');
});

it('sends a file with no words at all', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->set('files', [UploadedFile::fake()->image('manifest.png')])
        ->call('send')
        ->assertHasNoErrors();

    expect(Attachment::query()->count())->toBe(1);
});

it('shows the refusal against the field rather than throwing', function (): void {
    config()->set('filum.attachments.max_size', 10);

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->set('files', [UploadedFile::fake()->create('huge.pdf', 40, 'application/pdf')])
        ->set('body', 'too big')
        ->call('send')
        ->assertHasErrors('files');

    expect(Message::query()->where('body', 'too big')->exists())->toBeFalse();
});

it('drops a picked file before it is sent', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->set('files', [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')])
        ->call('unpick', 0)
        ->assertCount('files', 1);
});

it('hides the paperclip when attachments are switched off', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->assertSeeHtml('filum-attach');

    config()->set('filum.attachments.enabled', false);

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->assertDontSeeHtml('filum-attach');
});

it('serves a file to a participant', function (): void {
    app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'here',
        null,
        app(Heyosseus\Filum\Attachments\Attachments::class)->accept(
            $this->conversation,
            [UploadedFile::fake()->image('m.png')],
        ),
    );

    $file = Attachment::query()->firstOrFail();

    $this->get(route('filament.filum-test.filum.attachment', ['attachment' => $file->id]))
        ->assertSuccessful();
});

it('does not admit that a file exists to somebody outside the conversation', function (): void {
    app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'here',
        null,
        app(Heyosseus\Filum\Attachments\Attachments::class)->accept(
            $this->conversation,
            [UploadedFile::fake()->image('m.png')],
        ),
    );

    $file = Attachment::query()->firstOrFail();

    // 404 rather than 403: whether a given file exists is itself something only a
    // participant should learn.
    $this->actingAs($this->user('Dato'), 'panel')
        ->get(route('filament.filum-test.filum.attachment', ['attachment' => $file->id]))
        ->assertNotFound();
});

it('does not serve a file that is not there', function (): void {
    $this->get(route('filament.filum-test.filum.attachment', ['attachment' => 999999]))
        ->assertNotFound();
});

it('does not serve a file whose bytes have gone from the disk', function (): void {
    app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'here',
        null,
        app(Heyosseus\Filum\Attachments\Attachments::class)->accept(
            $this->conversation,
            [UploadedFile::fake()->image('m.png')],
        ),
    );

    $file = Attachment::query()->firstOrFail();
    Storage::disk('local')->delete($file->path);

    $this->get(route('filament.filum-test.filum.attachment', ['attachment' => $file->id]))
        ->assertNotFound();
});

it('does not serve a file to somebody the gate refuses', function (): void {
    app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'here',
        null,
        app(Heyosseus\Filum\Attachments\Attachments::class)->accept(
            $this->conversation,
            [UploadedFile::fake()->image('m.png')],
        ),
    );

    $file = Attachment::query()->firstOrFail();

    Heyosseus\Filum\Filum::auth(fn (): bool => false);

    $this->get(route('filament.filum-test.filum.attachment', ['attachment' => $file->id]))
        ->assertNotFound();
});

it('serves a document as a download rather than inline', function (): void {
    app(Messages::class)->send(
        $this->conversation,
        $this->nino,
        'the invoice',
        null,
        app(Heyosseus\Filum\Attachments\Attachments::class)->accept(
            $this->conversation,
            [UploadedFile::fake()->create('invoice.pdf', 2, 'application/pdf')],
        ),
    );

    $file = Attachment::query()->firstOrFail();

    $this->get(route('filament.filum-test.filum.attachment', ['attachment' => $file->id]))
        ->assertSuccessful()
        ->assertDownload('invoice.pdf');
});
