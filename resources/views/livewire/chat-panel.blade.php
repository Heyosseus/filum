{{-- The one chat. $mode decides the chrome around it and nothing else. --}}
<div class="filum-root filum-mode-{{ $mode }}" wire:poll.{{ $poll }}s="tick">
    @if ($mode === 'overlay')
        <button
            type="button"
            class="filum-trigger"
            wire:click="toggle"
            aria-expanded="{{ $open ? 'true' : 'false' }}"
            aria-label="{{ $open ? __('filum::filum.overlay.close') : __('filum::filum.overlay.open') }}"
        >
            <span class="filum-trigger-label">{{ __('filum::filum.nav.chat') }}</span>

            @php($unread = $colleagues->sum('unread'))

            @if ($unread > 0)
                <span class="filum-badge" title="{{ __('filum::filum.overlay.unread', ['count' => $unread]) }}">
                    {{ $unread }}
                </span>
            @endif
        </button>
    @endif

    @if ($mode === 'page' || $open)
        <div class="filum-panel">
            @include('filum::partials.sidebar', ['colleagues' => $colleagues])

            <section class="filum-conversation">
                @if ($partner === null)
                    <p class="filum-empty">{{ __('filum::filum.conversation.none_selected') }}</p>
                @else
                    @include('filum::partials.thread', ['thread' => $thread, 'me' => $me, 'partner' => $partner])
                    @include('filum::partials.composer')
                @endif
            </section>
        </div>
    @endif
</div>
