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

        Schema::table('filum_conversations', function (Blueprint $table) use ($users): void {
            // Explicit rather than inferred from a null key: the distinction backs
            // authorization and the board, and a load-bearing fact should not live
            // in the absence of a value.
            $table->string('kind')->default('direct')->after('id');

            // Groups only. A direct conversation leaves both null.
            $table->string('name')->nullable()->after('kind');
            $table->foreignIdFor($users, 'owner_id')->nullable()->after('name');
        });

        Schema::table('filum_participants', function (Blueprint $table) use ($users): void {
            // The default backfills every existing row as a full member, which is
            // exactly what they are.
            $table->string('state')->default('joined')->after('user_id');
            $table->foreignIdFor($users, 'invited_by_id')->nullable()->after('state');
            $table->timestamp('joined_at')->nullable()->after('invited_by_id');

            // Serves "my groups" and "my invitations", both of which filter by state.
            $table->index(['user_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::table('filum_participants', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'state']);
            $table->dropColumn(['state', 'invited_by_id', 'joined_at']);
        });

        Schema::table('filum_conversations', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'name', 'owner_id']);
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
