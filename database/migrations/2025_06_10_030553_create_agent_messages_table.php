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
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');        // ID to group a single session's messages
            $table->string('agent_name');        // e.g. "Manager", "Designer", "Engineer"
            $table->string('role');              // "system", "user", "assistant", or "function"
            $table->text('content')->nullable(); // message content (for user/assistant)
            $table->string('function_name')->nullable(); // if role is 'function', store function call name
            $table->json('function_args')->nullable();   // store function call arguments
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
