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

it('publishes the migrations', function (): void {
    $this->artisan('filum:install')->assertSuccessful();

    $published = File::glob(database_path('migrations/*filum*.php'));

    expect($published)->not->toBeEmpty(
        'Nothing matched. Directory holds: '.implode(', ', array_map(
            basename(...),
            File::glob(database_path('migrations/*.php')),
        )),
    );
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
