<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old CHECK constraint if it exists (MySQL 8.0.16+)
        $this->dropCheckConstraint('chk_profiles_uid');

        // Extend column from char(6) to char(8)
        Schema::table('profiles', static function (Blueprint $table): void {
            $table->char('profile_id', 8)->change();
        });

        // Add CHECK constraint allowing 6-8 char hex (existing 6-char + new 8-char)
        DB::statement("ALTER TABLE dns_profiles ADD CONSTRAINT chk_profiles_uid CHECK (profile_id REGEXP '^[0-9a-f]{6,8}$')");
    }

    public function down(): void
    {
        $this->dropCheckConstraint('chk_profiles_uid');

        Schema::table('profiles', static function (Blueprint $table): void {
            $table->char('profile_id', 6)->change();
        });

        DB::statement("ALTER TABLE dns_profiles ADD CONSTRAINT chk_profiles_uid CHECK (profile_id REGEXP '^[0-9a-f]{6}$')");
    }

    /**
     * Drop a CHECK constraint if it exists (MySQL 8.0.16+).
     */
    private function dropCheckConstraint(string $name): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?',
            ['dns_profiles', $name]
        );

        if ($exists && $exists->c > 0) {
            DB::statement("ALTER TABLE dns_profiles DROP CONSTRAINT {$name}");
        }
    }
};
