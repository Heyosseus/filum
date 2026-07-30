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

    <button type="submit" class="filum-send">{{ __('filum::filum.composer.send') }}</button>

    @error('body')
        <p class="filum-error">{{ $message }}</p>
    @enderror
</form>
