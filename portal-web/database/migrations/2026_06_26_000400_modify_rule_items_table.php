<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rule_items', 'confidence')) {
            return;
        }

        Schema::table('rule_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rule_items', 'tag')) {
                $table->string('tag', 50)->nullable()->after('category');
            }
            if (! Schema::hasColumn('rule_items', 'source_domain')) {
                $table->string('source_domain', 255)->nullable()->after('tag');
            }
            if (! Schema::hasColumn('rule_items', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('source_domain');
            }
            if (! Schema::hasColumn('rule_items', 'confidence')) {
                $table->string('confidence', 20)->default('high')->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rule_items', function (Blueprint $table) {
            if (Schema::hasColumn('rule_items', 'confidence')) {
                $table->dropColumn('confidence');
            }
            if (Schema::hasColumn('rule_items', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('rule_items', 'source_domain')) {
                $table->dropColumn('source_domain');
            }
            if (Schema::hasColumn('rule_items', 'tag')) {
                $table->dropColumn('tag');
            }
        });
    }
};
