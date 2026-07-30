{{-- The one chat. $mode decides the chrome around it and nothing else. --}}
@php($unread = $colleagues->sum('unread'))
@php($partnerOnline = (bool) ($colleagues->firstWhere('id', $selected)['online'] ?? false))

{{--
    In page mode both panes are on screen. In the drawer only one is, because two
    panes inside 26rem is two panes nobody can use: the board stands in until a
    colleague is picked, and the thread header carries the way back.
--}}
@php($showBoard = $mode === 'page' || $selected === null)
@php($showThread = $mode === 'page' || $selected !== null)

<div class="filum-root filum-mode-{{ $mode }}" wire:poll.{{ $poll }}s="tick">
    @if ($mode === 'overlay')
        <button
            type="button"
            class="filum-tab"
            wire:click="toggle"
            aria-expanded="{{ $open ? 'true' : 'false' }}"
            aria-label="{{ $open ? __('filum::filum.overlay.close') : __('filum::filum.overlay.open') }}"
        >
            <span class="filum-tab-label">{{ __('filum::filum.nav.chat') }}</span>

            @if ($unread > 0)
                <span class="filum-count" title="{{ __('filum::filum.overlay.unread', ['count' => $unread]) }}">
                    {{ $unread }}
                </span>
            @endif
        </button>
    @endif

    @if ($mode === 'page' || $open)
        <div class="filum-panel">
            @if ($showBoard)
                @include('filum::partials.sidebar', ['colleagues' => $colleagues])
            @endif

            @if ($showThread)
                <section class="filum-conversation">
                    @if ($partner === null)
                        <p class="filum-empty">{{ __('filum::filum.conversation.none_selected') }}</p>
                    @else
                        @include('filum::partials.thread', [
                            'thread' => $thread,
                            'me' => $me,
                            'partner' => $partner,
                            'partnerOnline' => $partnerOnline,
                        ])

                        @include('filum::partials.composer')
                    @endif
                </section>
            @endif
        </div>
    @endif
</div>
