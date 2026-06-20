<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_audit_logs') && Schema::hasColumn('admin_audit_logs', 'actor_name') && ! Schema::hasColumn('admin_audit_logs', 'actor_username')) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->renameColumn('actor_name', 'actor_username');
            });
        }

        if (! Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table): void {
                $table->string('id', 40)->primary();
                $table->string('level', 20)->default('info');
                $table->string('status', 20)->default('open');
                $table->string('title', 160);
                $table->text('message')->nullable();
                $table->json('context')->nullable();
                $table->string('source', 80)->nullable();
                $table->string('related_type', 80)->nullable();
                $table->string('related_id', 80)->nullable();
                $table->string('acknowledged_by', 36)->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'level', 'created_at']);
            });
        }

        if (Schema::hasTable('alerts') && DB::table('alerts')->count() === 0) {
            DB::table('alerts')->insert([
                'id' => 'alt_bootstrap_0001',
                'level' => 'warning',
                'status' => 'open',
                'title' => 'Bootstrap alert',
                'message' => 'Initial alert record for admin workflow verification.',
                'source' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');

        if (Schema::hasTable('admin_audit_logs') && Schema::hasColumn('admin_audit_logs', 'actor_username') && ! Schema::hasColumn('admin_audit_logs', 'actor_name')) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->renameColumn('actor_username', 'actor_name');
            });
        }
    }
};
