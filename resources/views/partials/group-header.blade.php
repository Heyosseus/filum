{{--
    A group is named by its own name and says nothing about presence: "online" is
    a fact about a person, and a room full of people does not have one. Leaving is
    offered to everybody; deleting only to the owner, because only the owner can.
--}}
<header class="filum-thread-head">
    <button type="button" class="filum-back" wire:click="deselect">
        &larr; {{ __('filum::filum.sidebar.heading') }}
    </button>

    <span class="filum-avatar filum-avatar-group" aria-hidden="true">#</span>

    <h3 class="filum-thread-name">{{ $group->name }}</h3>

    <span class="filum-eyebrow filum-thread-state">
        {{ __('filum::filum.sidebar.members', ['count' => $group->participants()->where('state', 'joined')->count()]) }}
    </span>

    <button type="button" class="filum-back" wire:click="leaveGroup">
        {{ __('filum::filum.sidebar.leave_group') }}
    </button>

    @if ((string) $group->owner_id === (string) app(\Heyosseus\Filum\Contracts\UserProvider::class)->id($me))
        <button type="button" class="filum-back" wire:click="deleteGroup">
            {{ __('filum::filum.sidebar.delete_group') }}
        </button>
    @endif
</header>
