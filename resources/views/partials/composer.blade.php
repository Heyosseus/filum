{{--
    Enter sends, shift+enter breaks the line: the convention people already have.

    The textarea carries wire:ignore because this component polls. A poll is a
    re-render, and a re-render morphs the DOM back to what the server last knew --
    which, with a deferred wire:model, is an empty box. Half a sentence would
    vanish every few seconds. Ignoring the element leaves what you are typing
    alone; wire:model still reports it on the next request, and the component
    empties it explicitly once a send has succeeded.
--}}
<form
    class="filum-composer"
    wire:submit="send"
    x-on:filum-composer-cleared.window="$refs.body.value = ''"
>
    @if ($replying !== null)
        {{--
            Above the box rather than inside it, so what you are answering stays
            legible while you type and the answer is never mistaken for the quote.
        --}}
        <div class="filum-replying">
            <span class="filum-replying-mark" aria-hidden="true">&#8626;</span>

            <span class="filum-replying-what">
                {{ __('filum::filum.conversation.replying_to') }}
                <em>{{ \Illuminate\Support\Str::limit($replying->body, 60) ?: __('filum::filum.composer.attachment') }}</em>
            </span>

            <button
                type="button"
                class="filum-replying-drop"
                wire:click="cancelReply"
                aria-label="{{ __('filum::filum.conversation.reply_cancel') }}"
                title="{{ __('filum::filum.conversation.reply_cancel') }}"
            >&times;</button>
        </div>
    @endif

    @if ($files !== [])
        {{-- Picked but not sent: each one droppable until the message goes. --}}
        <ul class="filum-picked">
            @foreach ($files as $index => $picked)
                <li wire:key="filum-picked-{{ $index }}">
                    <span class="filum-picked-name">{{ $picked->getClientOriginalName() }}</span>

                    <button
                        type="button"
                        wire:click="unpick({{ $index }})"
                        aria-label="{{ __('filum::filum.composer.remove_file') }}"
                        title="{{ __('filum::filum.composer.remove_file') }}"
                    >&times;</button>
                </li>
            @endforeach
        </ul>
    @endif

    <label class="filum-visually-hidden" for="filum-body">
        {{ __('filum::filum.composer.placeholder') }}
    </label>

    <textarea
        id="filum-body"
        class="filum-input"
        wire:model="body"
        wire:ignore
        x-ref="body"
        x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
        rows="2"
        placeholder="{{ __('filum::filum.composer.placeholder') }}"
        maxlength="{{ config('filum.messages.max_length', 2000) }}"
    ></textarea>

    @if ($attaching)
        {{--
            A label wrapping a hidden input rather than a button: the browser's own
            file picker needs no JavaScript to open, and the label is the control.
        --}}
        <label class="filum-attach" title="{{ __('filum::filum.composer.attach') }}">
            <span class="filum-visually-hidden">{{ __('filum::filum.composer.attach') }}</span>

            <input type="file" wire:model="files" multiple>

            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M13.5 7 8.2 12.3a1.9 1.9 0 0 0 2.7 2.7l5.6-5.6a3.4 3.4 0 0 0-4.8-4.8l-5.9 5.9a4.9 4.9 0 0 0 6.9 6.9l4.3-4.3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <span class="filum-attach-busy" wire:loading wire:target="files" aria-hidden="true"></span>
        </label>
    @endif

    <button type="submit" class="filum-send">{{ __('filum::filum.composer.send') }}</button>

    @error('body')
        <p class="filum-error">{{ $message }}</p>
    @enderror

    @error('files')
        <p class="filum-error">{{ $message }}</p>
    @enderror

    @error('files.*')
        <p class="filum-error">{{ $message }}</p>
    @enderror
</form>
