{{--
    The rooms, above the people. Naming a new one is an inline field rather than a
    modal: a modal inside a Livewire component inside the drawer is exactly where
    Filament 4 and 5 diverge, and there is nothing here worth that risk.
--}}
@if (config('filum.groups.enabled', true))
    <section class="filum-shift">
        <div class="filum-shift-head">
            <h3 class="filum-eyebrow">{{ __('filum::filum.sidebar.groups') }}</h3>
            <span class="filum-eyebrow filum-shift-count">{{ count($board->groups) }}</span>
        </div>

        <ul class="filum-people">
            @foreach ($board->groups as $group)
                <li>
                    <button
                        type="button"
                        class="filum-person @if ($conversationId === $group['id']) filum-person-active @endif"
                        wire:click="selectConversation({{ $group['id'] }})"
                        wire:key="filum-group-{{ $group['id'] }}"
                    >
                        <span class="filum-hash" aria-hidden="true">#</span>
                        <span class="filum-person-name">{{ $group['name'] }}</span>

                        @if ($group['unread'] > 0)
                            <span class="filum-count">{{ $group['unread'] }}</span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>

        {{--
            wire:ignore for the same reason the composer's textarea carries it:
            this component polls, a poll is a re-render, and a re-render morphs a
            deferred wire:model back to the server's empty string -- so a name
            half-typed would vanish every few seconds. The component empties the
            box explicitly, and only once a group was actually made.
        --}}
        <form
            class="filum-new-group"
            wire:submit="createGroup"
            x-on:filum-group-name-cleared.window="$refs.groupName.value = ''"
        >
            <input
                type="text"
                class="filum-search"
                wire:model="groupName"
                wire:ignore
                x-ref="groupName"
                placeholder="{{ __('filum::filum.sidebar.new_group') }}"
                aria-label="{{ __('filum::filum.sidebar.group_name') }}"
                maxlength="120"
            >

            @error('groupName')
                <p class="filum-error">{{ $message }}</p>
            @enderror
        </form>
    </section>
@endif
