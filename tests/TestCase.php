<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Tests;

use Heyosseus\Filum\Filum;
use Heyosseus\Filum\FilumServiceProvider;
use Heyosseus\Filum\Tests\Fixtures\User;
use Illuminate\Contracts\Config\Repository;
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
