<?php

declare(strict_types=1);

use Sofy\Database\Schema\Blueprint;
use Sofy\Database\Schema\Schema;

/**
 * Messenger tables: channels (direct 1:1 or named groups), their participants,
 * and messages. Driver-agnostic via the Schema builder.
 */
return new class {
    public function up(): void
    {
        Schema::create('chat_channels', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('direct');   // 'direct' | 'group'
            $table->string('name')->nullable();           // group name; null for DMs
            $table->string('dm_key')->nullable();         // canonical "minId:maxId" for DMs, unique
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('dm_key');
        });

        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id');
            $table->datetime('last_read_at')->nullable(); // for unread counts
            $table->timestamps();
            $table->index(['channel_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id');        // sender
            $table->text('body');
            $table->timestamps();
            $table->index(['channel_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_channels');
    }
};
