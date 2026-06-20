<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id');
            $table->string('feature_key', 80);
            $table->string('feature_value', 255)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['plan_id','feature_key'], 'uniq_plan_feature');
            $table->foreign('plan_id', 'fk_plan_features_plan')->references('id')->on('plans')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
