<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->unique();
            $table->string('name');
            $table->string('provider_name');
            $table->integer('context_length')->nullable();
            $table->integer('max_completion_tokens')->nullable();
            $table->json('pricing')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();



            $table->index('provider_name');
            $table->foreign('provider_name')
                ->references('name')->on('provider_icons')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
