{{--
    The ledger. One column, times in a fixed tabular gutter, sender named only
    when it changes, and a divider wherever the day does. Own messages carry an
    accent rail and a wash of the same colour instead of flipping to the other
    side: at drawer width there is no other side, and a log a person can scan
    top-to-bottom is worth more here than the messenger convention.
--}}

{{--
    Who said it, resolved once. In a direct conversation there is only one other
    person, so the name is known outright; in a group it has to be looked up, and
    it is looked up per distinct sender rather than per message so that a long
    thread stays one query each rather than one per line.
--}}
@php
    $users = app(\Heyosseus\Filum\Contracts\UserProvider::class);
    $partnerName = $partner === null ? '' : $users->name($partner);
    $names = [];

    if ($group !== null) {
        foreach ($thread->pluck('sender_id')->unique() as $senderId) {
            $sender = $users->find((string) $senderId);
            $names[(string) $senderId] = $sender === null ? '' : $users->name($sender);
        }
    }
@endphp

{{--
    A group is named by its own name and says nothing about presence: "online" is
    a fact about a person, and a room full of people does not have one.
--}}
@if ($group !== null)
    <header class="filum-thread-head">
        <button type="button" class="filum-back" wire:click="deselect">
            &larr; {{ __('filum::filum.sidebar.heading') }}
        </button>

        <span class="filum-avatar filum-avatar-group" aria-hidden="true">#</span>

        <h3 class="filum-thread-name">{{ $group->name }}</h3>
    </header>
@else
    <header class="filum-thread-head">
        <button type="button" class="filum-back" wire:click="deselect">
            &larr; {{ __('filum::filum.sidebar.heading') }}
        </button>

        <span class="filum-avatar @if ($partnerOnline) filum-avatar-live @endif">
            {{ mb_strtoupper(mb_substr($partnerName, 0, 1)) }}
        </span>

        <h3 class="filum-thread-name">{{ $partnerName }}</h3>

        <span class="filum-eyebrow filum-thread-state @if ($partnerOnline) filum-thread-state-live @endif">
            {{ $partnerOnline ? __('filum::filum.sidebar.online') : __('filum::filum.sidebar.offline') }}
        </span>
    </header>
@endif

<div class="filum-thread filum-scroll">
    @if ($thread->isEmpty())
        <p class="filum-empty">{{ __('filum::filum.conversation.empty') }}</p>
    @else
        {{-- Offered only when something actually precedes what is on screen. --}}
        @if ($hasOlder)
            <button type="button" class="filum-older" wire:click="loadOlder">
                {{ __('filum::filum.conversation.load_older') }}
            </button>
        @endif

        <ol class="filum-log">
            @php($previousDay = null)
            @php($previousSender = null)

            @foreach ($thread as $message)
                @php($mine = (string) $message->sender_id === (string) $me->getAuthIdentifier())
                @php($sentAt = $message->created_at)
                @php($day = $sentAt?->toDateString())

                @if ($day !== null && $day !== $previousDay)
                    <li class="filum-day" wire:key="filum-day-{{ $day }}">
                        <span class="filum-eyebrow">
                            @if ($sentAt->isToday())
                                {{ __('filum::filum.conversation.today') }}
                            @elseif ($sentAt->isYesterday())
                                {{ __('filum::filum.conversation.yesterday') }}
                            @else
                                {{ $sentAt->isoFormat('D MMMM YYYY') }}
                            @endif
                        </span>
                    </li>

                    @php($previousSender = null)
                @endif

                <li
                    class="filum-row @if ($mine) filum-row-mine @endif"
                    wire:key="filum-message-{{ $message->id }}"
                >
                    <time
                        class="filum-when"
                        datetime="{{ $sentAt?->toIso8601String() }}"
                        title="{{ $sentAt === null ? '' : __('filum::filum.conversation.sent_at', ['time' => $sentAt->isoFormat('LLL')]) }}"
                    >{{ $sentAt?->format('H:i') }}</time>

                    <div class="filum-said">
                        @if ($message->sender_id !== $previousSender)
                            <span class="filum-who">
                                @if ($mine)
                                    {{ __('filum::filum.conversation.you') }}
                                @else
                                    {{ $group === null ? $partnerName : ($names[(string) $message->sender_id] ?? '') }}
                                @endif
                            </span>
                        @endif

                        <p class="filum-what">{{ $message->body }}</p>
                    </div>
                </li>

                @php($previousDay = $day)
                @php($previousSender = $message->sender_id)
            @endforeach
        </ol>
    @endif
</div>
