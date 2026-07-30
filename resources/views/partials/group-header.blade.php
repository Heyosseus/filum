{{--
    A group is named by its own name and says nothing about presence: "online" is
    a fact about a person, and a room full of people does not have one. Leaving is
    offered to everybody; deleting only to the owner, because only the owner can.

    Inviting is a <details> disclosure rather than a modal. A Filament modal inside
    a Livewire component inside the drawer is exactly where Filament 4 and 5
    diverge, and a list of colleagues does not need one: closed it is a word, open
    it is the list, and the browser does the opening.
--}}
<header class="filum-thread-head">
    <button type="button" class="filum-back" wire:click="deselect">
        &larr; {{ __('filum::filum.sidebar.heading') }}
    </button>

    <span class="filum-avatar filum-avatar-group" aria-hidden="true">#</span>

    <h3 class="filum-thread-name">{{ $group->name }}</h3>

    {{-- Counted by the board, not again here: two answers to one question can
         disagree, and the board has already asked. --}}
    <span class="filum-eyebrow filum-thread-state">
        {{ trans_choice('filum::filum.sidebar.members', $members, ['count' => $members]) }}
    </span>

    {{-- Omitted rather than shown empty: there is nobody left to ask. --}}
    @if ($invitable !== [])
        <details class="filum-invite">
            <summary class="filum-back">{{ __('filum::filum.sidebar.invite') }}</summary>

            <ul class="filum-invite-list">
                @foreach ($invitable as $candidate)
                    <li wire:key="filum-invitable-{{ $candidate['id'] }}">
                        <button
                            type="button"
                            class="filum-accept"
                            wire:click="inviteToGroup('{{ $candidate['id'] }}')"
                        >
                            {{ $candidate['name'] }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    <button type="button" class="filum-back" wire:click="leaveGroup">
        {{ __('filum::filum.sidebar.leave_group') }}
    </button>

    @if ((string) $group->owner_id === (string) app(\Heyosseus\Filum\Contracts\UserProvider::class)->id($me))
        <button type="button" class="filum-back" wire:click="deleteGroup">
            {{ __('filum::filum.sidebar.delete_group') }}
        </button>
    @endif

    {{--
        Where the button is. Keyed 'group' rather than 'groupName' because the
        board carries groupName's error block, and the drawer swaps the board out
        while a thread is open -- so a refusal shown there would be shown nowhere.
    --}}
    @error('group')
        <p class="filum-error">{{ $message }}</p>
    @enderror
</header>
