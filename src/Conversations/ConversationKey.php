<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Conversations;

use Heyosseus\Filum\Exceptions\NotAConversation;

/**
 * The deterministic name of a conversation between a known set of people.
 *
 * Sorting before hashing is the whole trick: A-then-B and B-then-A must produce
 * the same key, or two people opening each other simultaneously would create two
 * conversations. Because the column is unique, the database settles that race
 * for us rather than a transaction having to.
 */
final class ConversationKey
{
    /**
     * @param  list<int|string>  $userIds
     *
     * @throws NotAConversation when fewer than two distinct people are named.
     */
    public static function for(array $userIds): string
    {
        $unique = array_values(array_unique(array_map(strval(...), $userIds)));

        if (count($unique) < 2) {
            throw NotAConversation::needsTwoPeople();
        }

        sort($unique, SORT_STRING);

        return hash('sha256', implode("\0", $unique));
    }
}
