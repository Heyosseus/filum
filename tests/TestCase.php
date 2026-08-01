<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests;

use Heyosseus\Filum\Filum;
use Heyosseus\Filum\FilumServiceProvider;
use Heyosseus\Filum\Tests\Fixtures\User;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // The gate is static, so one test's callback must not leak into the next.
        Filum::flush();

        // Applications that take performance seriously turn this on, and a
        // package that lazy loads inside their views does not merely run slowly
        // there -- it throws. Filum found that out from a consumer rather than
        // from its own suite once; enabling it here is how it does not happen
        // twice.
        Model::preventLazyLoading();
    }

    #[Override]
    protected function tearDown(): void
    {
        Filum::flush();

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [FilumServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app->make(Repository::class);

        $config->set('database.default', 'testing');

        // SQLite leaves foreign keys off unless asked. Without this the cascade
        // deletes in Filum's schema would silently do nothing under test while
        // working in production -- the tests would be weaker than the database.
        $config->set('database.connections.testing.foreign_key_constraints', true);
        $config->set('filum.users.model', User::class);

        // No broadcaster by default: the install Filum promises to work in.
        $config->set('broadcasting.default', 'null');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });

        // Laravel's notifications table, as an application that has opted into
        // Filament's notification bell would have it. Tests that cover the case
        // where it was never migrated drop it again.
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * A user who exists, for tests that need someone to be.
     */
    protected function user(string $name = 'Nino'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => mb_strtolower($name).'@example.com',
        ]);
    }

    /**
     * Point the application at a broadcaster that looks real.
     */
    protected function withBroadcaster(): void
    {
        $config = $this->app?->make(Repository::class);

        $config?->set('broadcasting.default', 'reverb');
        $config?->set('broadcasting.connections.reverb', ['driver' => 'reverb']);
    }
}
