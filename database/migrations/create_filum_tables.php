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

        Schema::create('filum_conversations', function (Blueprint $table): void {
            $table->id();

            // A deterministic key derived from the sorted participant ids. It makes
            // "the conversation between these two" one indexed lookup instead of a
            // self-join, and it is the unique index -- not a transaction -- that
            // settles two people opening each other at the same moment.
            $table->string('key')->nullable()->unique();

            // Denormalised so the sidebar can order and preview conversations
            // without touching the messages table.
            $table->timestamp('last_message_at')->nullable()->index();

            $table->timestamps();
        });

        Schema::create('filum_participants', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('filum_conversations')->cascadeOnDelete();
            $table->foreignIdFor($users, 'user_id');

            // Per-participant read state, which is what gives unread counts
            // without a row per message per reader.
            $table->timestamp('last_read_at')->nullable();

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('filum_messages', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('filum_conversations')->cascadeOnDelete();
            $table->foreignIdFor($users, 'sender_id');
            $table->text('body');
            $table->timestamps();

            // Serves both the newest page and keyset scrollback.
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('filum_presence', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignIdFor($users, 'user_id')->unique();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filum_presence');
        Schema::dropIfExists('filum_messages');
        Schema::dropIfExists('filum_participants');
        Schema::dropIfExists('filum_conversations');
    }

    /**
     * The application's user model, so that foreignIdFor derives the right key
     * type -- integer, UUID or ULID -- without Filum having to care which.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    private function userModel(): string
    {
        $model = config('filum.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            throw new RuntimeException('filum.users.model must name an Eloquent model class.');
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        return $model;
    }
};
