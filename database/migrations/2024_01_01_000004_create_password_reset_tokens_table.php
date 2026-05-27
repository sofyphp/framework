<?php

declare(strict_types=1);

use Sofy\Database\Schema\Blueprint;
use Sofy\Database\Schema\Schema;

return new class {
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->unique();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
