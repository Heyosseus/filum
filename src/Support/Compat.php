<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Support;

use Composer\InstalledVersions;

/**
 * The one place that knows Filament 4 from Filament 5.
 *
 * The majors differ in the Livewire they carry -- 3.5 against 4.1 -- and in
 * parts of the render-hook surface. Confining the branching here means the rest
 * of Filum is written against a single API, and that this file is the only one
 * that needs revisiting when a new major lands.
 */
final class Compat
{
    /**
     * The installed Filament major, or the newest supported when it cannot be
     * determined -- an unknown Filament is far more likely to be new than old.
     */
    public static function filamentMajor(): int
    {
        return self::majorOf('filament/filament', 5);
    }

    /**
     * The installed Livewire major. Filament 4 brings Livewire 3, Filament 5
     * brings Livewire 4.
     */
    public static function livewireMajor(): int
    {
        return self::majorOf('livewire/livewire', 4);
    }

    /**
     * The render hook Filum hangs the overlay from.
     *
     * Both majors expose BODY_END, so this resolves through the class constant
     * rather than a hardcoded string, and stays correct if the value changes.
     */
    public static function bodyEndHook(): string
    {
        /** @var string */
        return constant('Filament\View\PanelsRenderHook::BODY_END');
    }

    private static function majorOf(string $package, int $fallback): int
    {
        if (! InstalledVersions::isInstalled($package)) {
            return $fallback;
        }

        $version = InstalledVersions::getPrettyVersion($package);

        if (! is_string($version) || ! preg_match('/(\d+)/', $version, $matches)) {
            return $fallback;
        }

        return (int) $matches[1];
    }
}
