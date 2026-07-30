{{-- Only exists when you have one: an empty section is a question nobody asked. --}}
@if ($board->invitations !== [])
    <section class="filum-shift">
        <div class="filum-shift-head">
            <h3 class="filum-eyebrow">{{ __('filum::filum.sidebar.invitations') }}</h3>
            <span class="filum-eyebrow filum-shift-count">{{ count($board->invitations) }}</span>
        </div>

        <ul class="filum-people">
            @foreach ($board->invitations as $invitation)
                <li class="filum-invitation" wire:key="filum-invitation-{{ $invitation['id'] }}">
                    <span class="filum-person-name">{{ $invitation['name'] }}</span>
                    <span class="filum-invited-by">{{ __('filum::filum.sidebar.invited_by', ['name' => $invitation['invitedBy']]) }}</span>

                    <button type="button" class="filum-accept" wire:click="acceptInvitation({{ $invitation['id'] }})">
                        {{ __('filum::filum.sidebar.accept') }}
                    </button>

                    <button type="button" class="filum-decline" wire:click="declineInvitation({{ $invitation['id'] }})">
                        {{ __('filum::filum.sidebar.decline') }}
                    </button>
                </li>
            @endforeach
        </ul>
    </section>
@endif
