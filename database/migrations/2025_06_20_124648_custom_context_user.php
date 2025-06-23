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
        Schema::table('users',function(Blueprint $table)
        {
            $table->longText('user_context')->nullable();
            $table->longText('ai_behavior')->nullable();
            $table->json('custom_commands')->nullable();
        /**
            *
            *{
            *"command": "/token",
            *   "description": ".."
            *},
            *{
            * "command": "/token",
            *  "description": "...."
            *}
             */
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
