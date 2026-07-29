<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\ConversationKey;
use Heyosseus\Filum\Exceptions\NotAConversation;

it('gives the same key whichever order the participants arrive in', function (): void {
    expect(ConversationKey::for([7, 3]))->toBe(ConversationKey::for([3, 7]));
});

it('gives different keys to different pairs', function (): void {
    expect(ConversationKey::for([1, 2]))->not->toBe(ConversationKey::for([1, 3]));
});

it('treats string and integer ids as the same person', function (): void {
    expect(ConversationKey::for(['4', 9]))->toBe(ConversationKey::for([4, '9']));
});

it('works with uuid keys', function (): void {
    $a = '0195e2a0-1f4e-7000-8000-000000000001';
    $b = '0195e2a0-1f4e-7000-8000-000000000002';

    expect(ConversationKey::for([$a, $b]))->toBe(ConversationKey::for([$b, $a]));
});

it('refuses a conversation with only one person in it', function (): void {
    ConversationKey::for([5, 5]);
})->throws(NotAConversation::class);

it('refuses an empty participant set', function (): void {
    ConversationKey::for([]);
})->throws(NotAConversation::class);
