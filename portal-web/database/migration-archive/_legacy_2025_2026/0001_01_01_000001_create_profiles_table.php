<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('user_id', 36);
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('team_id', 36)->nullable();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('default_action', 20)->default('allow');
            $table->string('block_response', 30)->default('nxdomain');
            $table->boolean('security_enabled')->default(true);
            $table->boolean('adblock_enabled')->default(false);
            $table->boolean('parental_enabled')->default(false);
            $table->boolean('privacy_enabled')->default(true);
            $table->boolean('safe_search_enabled')->default(false);
            $table->string('log_mode', 30)->default('full');
            $table->bigInteger('current_version')->default(0);
            $table->bigInteger('draft_version')->default(0);
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('profile_rules', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('profile_id', 36);
            $table->foreign('profile_id')->references('id')->on('profiles');
            $table->string('list_type', 20);
            $table->string('match_type', 20);
            $table->string('domain', 255);
            $table->string('normalized_domain', 255);
            $table->string('action', 20);
            $table->string('category', 50)->nullable();
            $table->boolean('enabled')->default(true);
            $table->text('note')->nullable();
            $table->string('created_by', 36);
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['profile_id', 'list_type', 'match_type', 'normalized_domain'], 'uniq_profile_rule_active');
        });

        Schema::create('profile_versions', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('profile_id', 36);
            $table->foreign('profile_id')->references('id')->on('profiles');
            $table->bigInteger('version');
            $table->string('status', 30)->default('draft');
            $table->string('checksum', 100);
            $table->json('config_json');
            $table->integer('rule_count')->default(0);
            $table->string('message', 255)->nullable();
            $table->string('published_by', 36)->nullable();
            $table->string('external_publish_id', 80)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'version'], 'uniq_profile_versions_profile_version');
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('user_id', 36);
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('profile_id', 36);
            $table->foreign('profile_id')->references('id')->on('profiles');
            $table->string('name', 100);
            $table->string('device_type', 50);
            $table->string('device_id', 255)->nullable();
            $table->string('public_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('profile_versions');
        Schema::dropIfExists('profile_rules');
        Schema::dropIfExists('profiles');
    }
};
