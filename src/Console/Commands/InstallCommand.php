<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Console\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'filum:install {--force : Overwrite files that already exist}';

    protected $description = 'Publish Filum\'s configuration and migrations.';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'filum-config',
            '--force' => (bool) $this->option('force'),
        ]);

        // The directory tag, which is every migration Filum has -- deliberately not
        // also the per-release tags, which publish files this one has just copied.
        // Publishing both would put two of each on disk, and migrate would abort on
        // the duplicate.
        $this->callSilently('vendor:publish', [
            '--tag' => 'filum-migrations',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->components->info(__('filum::filum.install.published'));
        $this->components->info(__('filum::filum.install.next'));
        $this->components->info(__('filum::filum.install.plugin'));

        return self::SUCCESS;
    }
}
