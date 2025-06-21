<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->integer('version_number');
            $table->longText('content');
            $table->text('change_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_versions');
    }
};
