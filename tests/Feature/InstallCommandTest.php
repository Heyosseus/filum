<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('publishes the configuration and says what to do next', function (): void {
    $this->artisan('filum:install')
        ->expectsOutputToContain(__('filum::filum.install.published'))
        ->expectsOutputToContain(__('filum::filum.install.next'))
        ->expectsOutputToContain(__('filum::filum.install.plugin'))
        ->assertSuccessful();

    expect(File::exists(config_path('filum.php')))->toBeTrue();
});

it('publishes the migrations under a date that sorts after the application\'s own', function (): void {
    $this->artisan('filum:install')->assertSuccessful();

    // The date prefix is not decoration. Laravel only rewrites a published
    // migration's date when the source name already carries one, and an undated
    // name sorts ahead of every application migration -- so the foreign key to
    // the users table would be created before that table exists.
    $published = File::glob(database_path('migrations/*_create_filum_tables.php'));

    expect($published)->not->toBeEmpty(
        'Nothing matched. Directory holds: '.implode(', ', array_map(
            basename(...),
            File::glob(database_path('migrations/*.php')),
        )),
    );

    expect(basename((string) $published[0]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_filum_tables\.php$/');
});

it('can be run again with force', function (): void {
    $this->artisan('filum:install')->assertSuccessful();
    $this->artisan('filum:install', ['--force' => true])->assertSuccessful();
});

afterEach(function (): void {
    File::delete(config_path('filum.php'));

    foreach (File::glob(database_path('migrations/*_create_filum_tables.php')) as $migration) {
        File::delete($migration);
    }
});
