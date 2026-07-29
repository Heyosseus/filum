<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Presence;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Heyosseus\Filum\Contracts\PresenceStore;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;

/**
 * Presence in a table rather than a cache entry.
 *
 * The sidebar's core job is enumerating who is around, and cache stores do not
 * enumerate. One upsert per admin per heartbeat, one indexed range scan to draw
 * the list, and no Redis required of anyone.
 */
final readonly class DatabasePresenceStore implements PresenceStore
{
    private const string TABLE = 'filum_presence';

    public function __construct(private Repository $config) {}

    public function beat(int|string $userId): void
    {
        $now = CarbonImmutable::now();

        DB::table(self::TABLE)->upsert(
            [[
                'user_id' => $userId,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['user_id'],
            ['last_seen_at', 'updated_at'],
        );
    }

    public function active(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('last_seen_at', '>=', $this->cutoff())
            ->orderBy('user_id')
            ->pluck('user_id');

        /** @var list<int|string> $ids */
        $ids = $rows->map(static fn (mixed $id): int|string => is_int($id) ? $id : (string) $id)
            ->values()
            ->all();

        return $ids;
    }

    public function seenAt(int|string $userId): ?CarbonInterface
    {
        $value = DB::table(self::TABLE)->where('user_id', $userId)->value('last_seen_at');

        if (! is_string($value) && ! $value instanceof CarbonInterface) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value : CarbonImmutable::parse($value);
    }

    /**
     * The moment before which a heartbeat no longer counts as "here".
     */
    private function cutoff(): CarbonImmutable
    {
        $ttl = $this->config->get('filum.presence.ttl', 180);

        return CarbonImmutable::now()->subSeconds(is_int($ttl) ? $ttl : 180);
    }
}
