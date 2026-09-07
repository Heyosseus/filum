<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room for ciphertext.
 *
 * Encryption roughly doubles a body -- padding, an iv, a mac, and base64 twice
 * over -- and MySQL's TEXT stops at 65,535 bytes. The default 2,000-character
 * limit fits either way, but messages.max_length is the consumer's to raise, and
 * a message that sends fine in plaintext must not start failing to insert the
 * day encryption is switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filum_messages', function (Blueprint $table): void {
            $table->longText('body')->change();
        });
    }

    public function down(): void
    {
        Schema::table('filum_messages', function (Blueprint $table): void {
            $table->text('body')->change();
        });
    }
};
