<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('security_data_items')) {
            return;
        }

        Schema::create('security_data_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('group_code', 40)->index();
            $table->string('value', 255);
            $table->string('note', 500)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['group_code', 'value'], 'uniq_security_data_group_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_data_items');
    }
};
