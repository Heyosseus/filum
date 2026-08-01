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

        Schema::table('filum_messages', function (Blueprint $table): void {
            // Null on delete rather than cascade: removing a message must not take
            // the answers to it with it. A reply whose parent is gone still says
            // something, and loses only the quote above it.
            $table->foreignId('reply_to_id')
                ->nullable()
                ->after('sender_id')
                ->constrained('filum_messages')
                ->nullOnDelete();
        });

        Schema::create('filum_attachments', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('message_id')->constrained('filum_messages')->cascadeOnDelete();
            $table->foreignIdFor($users, 'user_id');

            // The disk is stored per row rather than read from config at download
            // time: an application that changes disks must still be able to serve
            // what it already accepted.
            $table->string('disk');
            $table->string('path');

            // What the person called it, kept apart from where it landed. The path
            // is generated, so it is never safe to show and never safe to trust.
            $table->string('name');
            $table->string('mime');
            $table->unsignedBigInteger('size');

            $table->timestamps();

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filum_attachments');

        Schema::table('filum_messages', function (Blueprint $table): void {
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn('reply_to_id');
        });
    }

    /**
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
