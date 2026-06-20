<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('config_key', 120);
            $table->json('config_value')->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
            $table->unique('config_key', 'uniq_system_config_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
