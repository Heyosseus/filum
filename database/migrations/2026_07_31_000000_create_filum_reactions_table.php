<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = $this->userModel();

        Schema::create('filum_reactions', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('message_id')->constrained('filum_messages')->cascadeOnDelete();
            $table->foreignIdFor($users, 'user_id');

            // The emoji itself rather than a name or a code point: it is what gets
            // rendered, what gets compared, and what the configured set is written
            // in, so storing anything else would mean translating at every edge.
            $table->string('emoji', 16);

            $table->timestamps();

            // One of each emoji per person per message. The unique index is what
            // makes toggling safe under a double click -- the second insert loses
            // here rather than producing a second identical reaction.
            $table->unique(['message_id', 'user_id', 'emoji']);

            // Reactions are always read a thread at a time.
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filum_reactions');
    }

    /**
     * The application's user model, so that foreignIdFor derives the right key
     * type -- integer, UUID or ULID -- without Filum having to care which.
     *
     * @return class-string<Illuminate\Database\Eloquent\Model>
     */
    private function userModel(): string
    {
        $model = config('filum.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            throw new RuntimeException('filum.users.model must name an Eloquent model class.');
        }

        /** @var class-string<Illuminate\Database\Eloquent\Model> $model */
        return $model;
    }
};
