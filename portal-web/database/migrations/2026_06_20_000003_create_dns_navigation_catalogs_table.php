<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('navigation_catalogs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key', 80);
            $table->string('label_key', 160);
            $table->string('group_key', 50)->nullable();
            $table->string('path', 300)->nullable();
            $table->string('icon', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
            $table->unique('key', 'uniq_navigation_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_catalogs');
    }
};
