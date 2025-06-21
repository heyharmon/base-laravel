<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['user', 'assistant', 'system', 'function']);
            $table->text('content')->nullable();
            $table->string('function_name')->nullable();
            $table->json('function_arguments')->nullable();
            $table->json('function_response')->nullable();
            $table->text('reasoning')->nullable();
            $table->json('web_search_results')->nullable();
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->decimal('cost', 8, 4)->default(0);
            $table->string('job_id')->nullable();
            $table->enum('job_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
