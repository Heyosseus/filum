{{-- Enter sends, shift+enter breaks the line: the convention people already have. --}}
<form class="filum-composer" wire:submit="send">
    <label class="filum-visually-hidden" for="filum-body">
        {{ __('filum::filum.composer.placeholder') }}
    </label>

    <textarea
        id="filum-body"
        class="filum-input"
        wire:model="body"
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
