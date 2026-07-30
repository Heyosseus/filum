<?php

declare(strict_types=1);

return [

    'nav' => [
        'chat' => 'Chat',
    ],

    'page' => [
        'title' => 'Chat',
        'heading' => 'Chat',
    ],

    'sidebar' => [
        'heading' => 'Colleagues',
        'search' => 'Search colleagues',
        'empty' => 'Nobody else has an account yet.',
        'none_found' => 'No colleague matches that search.',
        'online' => 'Online',
        'offline' => 'Offline',
        'here' => 'Here now',
        'away' => 'Away',
        'last_seen' => 'Last seen :time',
        'never_seen' => 'Never signed in',
        'invitations' => 'Invitations',
        'groups' => 'Groups',
        'new_group' => 'New group',
        'group_name' => 'Group name',
        'members' => '{1} :count member|[2,*] :count members',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'invite' => 'Invite',
        'leave_group' => 'Leave group',
        'delete_group' => 'Delete group',
        'rename_group' => 'Rename group',
        'remove_member' => 'Remove',
        'pending' => 'Pending',
        'invited_by' => 'From :name',
        'group_needs_name' => 'Give the group a name.',
        'not_yours_to_delete' => 'Only the owner can delete this group.',
        'not_yours_to_rename' => 'Only the owner can rename this group.',
        'not_yours_to_remove' => 'Only the owner can remove someone.',
    ],

    'conversation' => [
        'none_selected' => 'Choose a colleague to start talking.',
        'empty' => 'No messages yet. Say something.',
        'load_older' => 'Load earlier messages',
        'you' => 'You',
        'sent_at' => 'Sent :time',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
    ],

    'composer' => [
        'placeholder' => 'Write a message…',
        'send' => 'Send',
        'too_long' => 'That message is longer than :max characters.',
        'rate_limited' => 'Too many messages. Try again in :seconds seconds.',
        'empty' => 'Write something first.',
    ],

    'notification' => [
        'open' => 'Open chat',
        'invited_body' => 'Invited you to :group',
    ],

    'overlay' => [
        'open' => 'Open chat',
        'close' => 'Close chat',
        'unread' => ':count unread',
    ],

    'errors' => [
        'not_participant' => 'You are not part of this conversation.',
        'missing_tables' => "Filum's tables are missing. Run: php artisan vendor:publish --tag=filum-migrations && php artisan migrate",
    ],

    'install' => [
        'published' => 'Published Filum configuration and migrations.',
        'next' => 'Next, run: php artisan migrate',
        'plugin' => 'Then add FilumPlugin::make() to your panel provider.',
        'done' => 'Filum is ready.',
    ],

];
