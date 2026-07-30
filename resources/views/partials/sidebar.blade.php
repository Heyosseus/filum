{{--
    The shift board. Grouping by presence rather than listing everyone flat is
    the point of the pane: in a back office the useful question is who can answer
    right now, and the group heading carries the count because that is a fact
    worth reading at a glance.
--}}
@php([$here, $away] = $colleagues->partition(fn (array $colleague): bool => $colleague['online']))

<aside class="filum-board">
    <div class="filum-board-head">
        <h2 class="filum-eyebrow">{{ __('filum::filum.sidebar.heading') }}</h2>

        <input
            type="search"
            class="filum-search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('filum::filum.sidebar.search') }}"
            aria-label="{{ __('filum::filum.sidebar.search') }}"
        >
    </div>

    <div class="filum-board-body filum-scroll">
        @if ($colleagues->isEmpty())
            <p class="filum-empty">
                {{ $search === '' ? __('filum::filum.sidebar.empty') : __('filum::filum.sidebar.none_found') }}
            </p>
        @else
            @foreach ([['filum::filum.sidebar.here', $here], ['filum::filum.sidebar.away', $away]] as [$label, $group])
                @if ($group->isNotEmpty())
                    <section class="filum-shift">
                        <div class="filum-shift-head">
                            <h3 class="filum-eyebrow">{{ __($label) }}</h3>
                            <span class="filum-eyebrow filum-shift-count">{{ $group->count() }}</span>
                        </div>

                        <ul class="filum-people">
                            @foreach ($group as $colleague)
                                <li>
                                    <button
                                        type="button"
                                        class="filum-person @if ($selected === $colleague['id']) filum-person-active @endif"
                                        wire:click="selectUser('{{ $colleague['id'] }}')"
                                        wire:key="filum-person-{{ $colleague['id'] }}"
                                    >
                                        <span class="filum-avatar @if ($colleague['online']) filum-avatar-live @endif">
                                            @if ($colleague['avatar'])
                                                <img src="{{ $colleague['avatar'] }}" alt="">
                                            @else
                                                {{ mb_strtoupper(mb_substr($colleague['name'], 0, 1)) }}
                                            @endif
                                        </span>

                                        <span class="filum-person-name">{{ $colleague['name'] }}</span>

                                        <span class="filum-visually-hidden">
                                            {{ $colleague['online'] ? __('filum::filum.sidebar.online') : __('filum::filum.sidebar.offline') }}
                                        </span>

                                        @if ($colleague['unread'] > 0)
                                            <span class="filum-count">{{ $colleague['unread'] }}</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        @endif
    </div>
</aside>
