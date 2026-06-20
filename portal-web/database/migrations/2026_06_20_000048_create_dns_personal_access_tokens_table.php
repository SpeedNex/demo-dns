<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tokenable_type', 120);
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name', 160);
            // V2.3: Sanctum 内部把 SHA256(token) 写入此列；不存明文，token 即 hash
            $table->string('token', 64);
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique('token', 'uniq_pat_token');
            $table->index(['tokenable_type','tokenable_id'], 'idx_pat_tokenable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
