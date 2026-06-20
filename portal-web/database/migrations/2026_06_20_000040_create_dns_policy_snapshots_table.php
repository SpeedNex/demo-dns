<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('policy_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('profile_id');
            $table->integer('version');
            $table->json('snapshot_json');
            $table->string('checksum', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['profile_id','version'], 'uniq_policy_snapshot');
            $table->index('user_id', 'idx_policy_snapshots_user');
            $table->foreign('user_id', 'fk_policy_snapshots_user')->references('uid')->on('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('profile_id', 'fk_policy_snapshots_profile')->references('id')->on('profiles')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_snapshots');
    }
};
