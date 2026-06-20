<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('geo_dns_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 255);
            $table->string('country', 8)->nullable();
            $table->string('region', 40)->nullable();
            $table->unsignedBigInteger('target_node_id')->nullable();
            $table->string('target_endpoint', 255)->nullable();
            $table->integer('weight')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index('domain', 'idx_geo_domain');
            $table->index('country', 'idx_geo_country');
            $table->index('target_node_id', 'idx_geo_node');
            $table->foreign('target_node_id', 'fk_geo_node')->references('id')->on('nodes')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_dns_mappings');
    }
};
