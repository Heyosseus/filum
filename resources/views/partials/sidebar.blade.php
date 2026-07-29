<aside class="filum-sidebar">
    <h2 class="filum-sidebar-heading">{{ __('filum::filum.sidebar.heading') }}</h2>

    <input
        type="search"
        class="filum-search"
        wire:model.live.debounce.300ms="search"
        placeholder="{{ __('filum::filum.sidebar.search') }}"
        aria-label="{{ __('filum::filum.sidebar.search') }}"
    >

    @if ($colleagues->isEmpty())
        <p class="filum-empty">
            {{ $search === '' ? __('filum::filum.sidebar.empty') : __('filum::filum.sidebar.none_found') }}
        </p>
    @else
        <ul class="filum-people">
            @foreach ($colleagues as $colleague)
                <li>
                    <button
                        type="button"
                        class="filum-person @if ($selected === $colleague['id']) filum-person-active @endif"
                        wire:click="selectUser('{{ $colleague['id'] }}')"
                        wire:key="filum-person-{{ $colleague['id'] }}"
                    >
                        <span class="filum-avatar">
                            @if ($colleague['avatar'])
                                <img src="{{ $colleague['avatar'] }}" alt="">
                            @else
                                {{ mb_substr($colleague['name'], 0, 1) }}
                            @endif
                        </span>

                        <span class="filum-person-name">{{ $colleague['name'] }}</span>

                        <span
                            class="filum-dot @if ($colleague['online']) filum-dot-online @endif"
                            title="{{ $colleague['online'] ? __('filum::filum.sidebar.online') : __('filum::filum.sidebar.offline') }}"
                        ></span>

                        @if ($colleague['unread'] > 0)
                            <span class="filum-badge">{{ $colleague['unread'] }}</span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</aside>
