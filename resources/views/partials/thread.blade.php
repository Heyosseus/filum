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
    use Illuminate\Support\Str;

    $users = app(\Heyosseus\Filum\Contracts\UserProvider::class);
    $partnerName = $partner === null ? '' : $users->name($partner);

    // Resolved once per render rather than per file: the route belongs to the
    // panel serving the request, so the name has to be built from its id.
    $panelId = \Filament\Facades\Filament::getCurrentPanel()?->getId();
    $attachmentUrl = static fn (\Heyosseus\Filum\Models\Attachment $file): string => $panelId === null
        ? '#'
        : route("filament.{$panelId}.filum.attachment", ['attachment' => $file->id]);
    $names = [];

    if ($group !== null) {
        foreach ($thread->pluck('sender_id')->unique() as $senderId) {
            $sender = $users->find((string) $senderId);
            $names[(string) $senderId] = $sender === null ? '' : $users->name($sender);
        }
    }
@endphp

@if ($group !== null)
    @include('filum::partials.group-header', [
        'group' => $group,
        'me' => $me,
        'members' => $members,
        'invitable' => $invitable,
        'roster' => $roster,
    ])
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

{{--
    A log opens at its newest line, the way you would pick up a paper one -- and
    a message arriving while you are looking should follow.

    But only while you are already at the bottom. Somebody who has scrolled up to
    read yesterday must not be yanked back down because a colleague typed, so the
    stick flag tracks how close to the foot the reader is and every automatic
    scroll asks it first. Reaching back for earlier messages leaves the flag
    false, which is exactly what keeps loadOlder from throwing you forward again.
--}}
<div
    class="filum-thread filum-scroll"
    wire:key="filum-thread-{{ $conversationId }}"
    x-data="{
        stick: true,
        foot() { this.$el.scrollTop = this.$el.scrollHeight },
        near() { return this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 48 },
    }"
    x-init="$nextTick(() => foot())"
    x-on:scroll.passive="stick = near()"
>
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

                        @if ($message->replyTo !== null)
                            {{--
                                A quote, not a link. Jumping the log to the parent
                                would move the reader away from the line they were
                                on; showing the gist keeps the answer readable
                                where it is.
                            --}}
                            <div class="filum-quote">
                                <span class="filum-quote-who">
                                    {{ (string) $message->replyTo->sender_id === (string) $me->getAuthIdentifier()
                                        ? __('filum::filum.conversation.you')
                                        : ($group === null ? $partnerName : ($names[(string) $message->replyTo->sender_id] ?? '')) }}
                                </span>
                                <span class="filum-quote-what">{{ Str::limit($message->replyTo->body, 70) }}</span>
                            </div>
                        @endif

                        @if ($message->body !== '')
                            <p class="filum-what">{{ $message->body }}</p>
                        @endif

                        @if ($message->attachments->isNotEmpty())
                            <div class="filum-files">
                                @foreach ($message->attachments as $file)
                                    <a
                                        class="filum-file @if ($file->isImage()) filum-file-image @endif"
                                        href="{{ $attachmentUrl($file) }}"
                                        target="_blank"
                                        rel="noopener"
                                        wire:key="filum-file-{{ $file->id }}"
                                    >
                                        @if ($file->isImage())
                                            <img src="{{ $attachmentUrl($file) }}" alt="{{ $file->name }}" loading="lazy">
                                        @else
                                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <path d="M11.5 2.5H6a1.5 1.5 0 0 0-1.5 1.5v12A1.5 1.5 0 0 0 6 17.5h8a1.5 1.5 0 0 0 1.5-1.5V6.5Z" stroke-linejoin="round" />
                                                <path d="M11.5 2.5v4h4" stroke-linejoin="round" />
                                            </svg>
                                            <span class="filum-file-name">{{ $file->name }}</span>
                                            <span class="filum-file-size">{{ $file->readableSize() }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if ($emoji !== [])
                            @php($said = $reactions[$message->id] ?? [])

                            {{--
                                Reactions someone left are content and always show.
                                The way to leave one is chrome, and chrome in a log
                                this dense has to earn its place -- so the opener is
                                revealed on hover rather than resident, the way
                                Slack and Discord reveal theirs. Fifty messages with
                                a permanent button each is fifty things competing
                                with the record.

                                It fades rather than appears: the row keeps its
                                height either way, so nothing under the pointer
                                shifts as it comes in.
                            --}}
                            <div
                                class="filum-reactions @if ($said !== []) filum-reactions-said @endif"
                                x-data="{ picking: false }"
                                x-on:keydown.escape="picking = false"
                            >
                                @foreach ($said as $reaction)
                                    <button
                                        type="button"
                                        class="filum-reaction @if ($reaction['mine']) filum-reaction-mine @endif"
                                        wire:click="react({{ $message->id }}, '{{ $reaction['emoji'] }}')"
                                        wire:key="filum-reaction-{{ $message->id }}-{{ $loop->index }}"
                                        title="{{ $reaction['mine'] ? __('filum::filum.reactions.remove') : __('filum::filum.reactions.add') }}"
                                    >
                                        <span class="filum-reaction-emoji" aria-hidden="true">{{ $reaction['emoji'] }}</span>
                                        <span class="filum-reaction-count">{{ $reaction['count'] }}</span>
                                    </button>
                                @endforeach

                                {{-- Revealed with the reaction opener, and for the same reason. --}}
                                <button
                                    type="button"
                                    class="filum-reaction-add"
                                    wire:click="reply({{ $message->id }})"
                                    aria-label="{{ __('filum::filum.conversation.reply') }}"
                                    title="{{ __('filum::filum.conversation.reply') }}"
                                >
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path d="M8 6.5 4 10l4 3.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M4 10h7a5 5 0 0 1 5 5v1" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>

                                <button
                                    type="button"
                                    class="filum-reaction-add"
                                    x-on:click="picking = ! picking"
                                    x-bind:aria-expanded="picking ? 'true' : 'false'"
                                    aria-label="{{ __('filum::filum.reactions.add') }}"
                                    title="{{ __('filum::filum.reactions.add') }}"
                                >
                                    {{-- Drawn rather than an icon font: the package ships one stylesheet and no build step. --}}
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <circle cx="10" cy="10" r="7.25" />
                                        <path d="M7.25 11.75a3.4 3.4 0 0 0 5.5 0" stroke-linecap="round" />
                                        <path d="M7.5 8.25h.01M12.5 8.25h.01" stroke-linecap="round" stroke-width="2" />
                                    </svg>
                                </button>

                                {{--
                                    The set opens inline, in the margin row, rather
                                    than floating over the thread. A popover would
                                    need clipping and z-index handling inside a
                                    scrolling log, and would cover the very messages
                                    you are reacting to -- the one thing a ledger
                                    should never do to itself.
                                --}}
                                <span
                                    class="filum-reaction-set"
                                    x-show="picking"
                                    x-cloak
                                    x-on:click.outside="picking = false"
                                >
                                    @foreach ($emoji as $option)
                                        <button
                                            type="button"
                                            wire:click="react({{ $message->id }}, '{{ $option }}')"
                                            wire:key="filum-pick-{{ $message->id }}-{{ $loop->index }}"
                                            x-on:click="picking = false"
                                            title="{{ $option }}"
                                        >{{ $option }}</button>
                                    @endforeach
                                </span>
                            </div>
                        @endif
                    </div>
                </li>

                @php($previousDay = $day)
                @php($previousSender = $message->sender_id)
            @endforeach
        </ol>

        {{--
            Keyed on the newest message, so Livewire replaces this node whenever
            one arrives and x-init runs again. Nothing else in the thread changes
            identity when a message lands, which is why the follow-the-foot
            behaviour hangs off a marker rather than off the list itself.
        --}}
        <div
            class="filum-foot"
            wire:key="filum-foot-{{ $thread->last()?->id }}"
            x-init="$nextTick(() => { if (stick) foot() })"
            aria-hidden="true"
        ></div>
    @endif
</div>
