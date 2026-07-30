{{-- The one chat. $mode decides the chrome around it and nothing else. --}}
@php($unread = collect($board->here)->sum('unread') + collect($board->away)->sum('unread') + collect($board->groups)->sum('unread'))

{{--
    Presence is which section a person is in rather than a flag on the row, so
    the partner's state is read back out of the board the sidebar is drawn from --
    one answer to "who is here", not two that can disagree.
--}}
@php($partnerId = $partner === null ? null : (string) app(\Heyosseus\Filum\Contracts\UserProvider::class)->id($partner))
@php($partnerOnline = $partnerId !== null && collect($board->here)->contains('id', $partnerId))

{{--
    In page mode both panes are on screen. In the drawer only one is, because two
    panes inside 26rem is two panes nobody can use: the board stands in until a
    conversation is picked, and the thread header carries the way back.
--}}
@php($showBoard = $mode === 'page' || $conversationId === null)
@php($showThread = $mode === 'page' || $conversationId !== null)

{{--
    Under a broadcaster the browser also listens, so a message appears the moment
    it is sent instead of on the next poll. The poll stays on underneath at the
    slower reconciliation interval -- a socket can drop, and a laptop can sleep.

    Guarded on window.Echo, not on the driver alone: an application can have a
    broadcaster configured server-side and no Echo client built into its panel
    assets, and in that case the reconciliation poll is all there is. Filum must
    keep working rather than wait for an event that will never arrive.
--}}
<div
    class="filum-root filum-mode-{{ $mode }}"
    wire:poll.{{ $poll }}s="tick"
    x-data="{ conversationChannel: null }"
    @if ($driver === 'broadcast')
        x-init="window.Echo?.channel('filum.presence').listen('.filum.presence.changed', () => $wire.$refresh())"
    @endif
>
    @if ($driver === 'broadcast' && $conversationId !== null)
        {{-- Keyed on the conversation so switching colleagues re-subscribes. --}}
        <div
            wire:key="filum-echo-{{ $conversationId }}"
            x-init="
                if (window.Echo) {
                    if (conversationChannel) window.Echo.leave(conversationChannel);
                    conversationChannel = 'filum.conversation.{{ $conversationId }}';
                    window.Echo.private(conversationChannel)
                        .listen('.filum.message.sent', () => $wire.received());
                }
            "
        ></div>
    @endif

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
                @include('filum::partials.sidebar', [
                    'board' => $board,
                    'conversationId' => $conversationId,
                    'partnerId' => $partnerId,
                ])
            @endif

            @if ($showThread)
                <section class="filum-conversation">
                    @if ($partner === null && $group === null)
                        <p class="filum-empty">{{ __('filum::filum.conversation.none_selected') }}</p>
                    @else
                        @include('filum::partials.thread', [
                            'thread' => $thread,
                            'me' => $me,
                            'partner' => $partner,
                            'partnerOnline' => $partnerOnline,
                            'group' => $group,
                            'members' => $members,
                            'invitable' => $invitable,
                            'roster' => $roster,
                            'hasOlder' => $hasOlder,
                        ])

                        @include('filum::partials.composer')
                    @endif
                </section>
            @endif
        </div>
    @endif
</div>
