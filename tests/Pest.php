<?php

declare(strict_types=1);

use Heyosseus\Filum\Tests\DisabledTestCase;
use Heyosseus\Filum\Tests\FilamentTestCase;
use Heyosseus\Filum\Tests\NoOverlayTestCase;
use Heyosseus\Filum\Tests\TestCase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class)->in(__DIR__.'/Unit', __DIR__.'/Feature');

// Anything that mounts a component or a page needs a real panel behind it.
uses(FilamentTestCase::class)->in(__DIR__.'/Filament');

// Both of these prove what happens at boot, so they need their own application.
uses(DisabledTestCase::class)->in(__DIR__.'/Disabled');
uses(NoOverlayTestCase::class)->in(__DIR__.'/NoOverlay');

/**
 * The notification payloads written for one user, oldest first.
 *
 * Read straight out of the table rather than through a fake, because the point of
 * Filum's notifications is that they land in the application's own notifications
 * table -- the one Filament's bell already reads.
 *
 * @return list<array<string, mixed>>
 */
function bell(int|string $userId): array
{
    return DB::table('notifications')
        ->where('notifiable_id', $userId)
        ->orderBy('created_at')
        ->get()
        ->map(function (object $row): array {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $row->data, true);

            return $data;
        })
        ->all();
}
