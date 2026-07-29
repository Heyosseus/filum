<div class="filum-thread">
    <header class="filum-thread-header">
        <h3>{{ app(\Heyosseus\Filum\Contracts\UserProvider::class)->name($partner) }}</h3>
    </header>

    @if ($thread->isEmpty())
        <p class="filum-empty">{{ __('filum::filum.conversation.empty') }}</p>
    @else
        <button type="button" class="filum-load-older" wire:click="loadOlder">
            {{ __('filum::filum.conversation.load_older') }}
        </button>

        <ol class="filum-messages">
            @foreach ($thread as $message)
                @php($mine = (string) $message->sender_id === (string) $me->getAuthIdentifier())

                <li
                    class="filum-message @if ($mine) filum-message-mine @endif"
                    wire:key="filum-message-{{ $message->id }}"
                >
                    <span class="filum-message-author">
                        {{ $mine ? __('filum::filum.conversation.you') : app(\Heyosseus\Filum\Contracts\UserProvider::class)->name($partner) }}
                    </span>

                    <p class="filum-message-body">{{ $message->body }}</p>

                    <time
                        class="filum-message-time"
                        datetime="{{ $message->created_at?->toIso8601String() }}"
                    >{{ $message->created_at?->diffForHumans() }}</time>
                </li>
            @endforeach
        </ol>
    @endif
</div>
