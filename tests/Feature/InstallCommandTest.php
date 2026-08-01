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

it('publishes only the group migration under its own tag, still date stamped', function (): void {
    // What an existing 0.1.1 install is told to run, and the whole reason the tag
    // exists. Publishing filum-migrations there would copy the create-tables
    // migration a second time -- Laravel looks for the source name, the install has
    // the stamped one -- and migrate would abort on the duplicate before it ever
    // reached this file.
    $this->artisan('vendor:publish', ['--tag' => 'filum-migrations-groups'])->assertSuccessful();

    $published = array_map(basename(...), File::glob(database_path('migrations/*.php')));

    expect($published)->toHaveCount(1)
        ->and($published[0])->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_add_group_conversations_to_filum_tables\.php$/');
});

it('publishes only the reactions migration under its own tag, still date stamped', function (): void {
    // The same reasoning as the groups tag: an install that already has the
    // earlier migrations must be able to take just the new one, because
    // republishing the rest duplicates them under fresh stamps.
    $this->artisan('vendor:publish', ['--tag' => 'filum-migrations-reactions'])->assertSuccessful();

    $published = array_map(basename(...), File::glob(database_path('migrations/*.php')));

    expect($published)->toHaveCount(1)
        ->and($published[0])->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_filum_reactions_table\.php$/');
});

it('publishes only the replies and attachments migration under its own tag', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'filum-migrations-replies'])->assertSuccessful();

    $published = array_map(basename(...), File::glob(database_path('migrations/*.php')));

    expect($published)->toHaveCount(1)
        ->and($published[0])->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_add_replies_and_attachments_to_filum_tables\.php$/');
});

it('can be run again with force', function (): void {
    $this->artisan('filum:install')->assertSuccessful();
    $this->artisan('filum:install', ['--force' => true])->assertSuccessful();
});

afterEach(function (): void {
    File::delete(config_path('filum.php'));

    // Every Filum migration, not just the two named *_filum_tables: a narrower
    // glob leaves the newest one behind and it leaks into the next test's count.
    foreach (File::glob(database_path('migrations/*filum*.php')) as $migration) {
        File::delete($migration);
    }
});
