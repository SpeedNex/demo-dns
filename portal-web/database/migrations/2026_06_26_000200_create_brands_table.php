<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('brands')) {
            return;
        }

        Schema::create('brands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 255)->unique();
            $table->string('name', 100);
            $table->string('category', 50)->nullable();
            $table->unsignedInteger('alexa_rank')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
