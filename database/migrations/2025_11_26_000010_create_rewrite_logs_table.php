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
        Schema::create('rewrite_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('article_joomla_id')->nullable();
            $table->string('article_title')->nullable();
            $table->enum('status', ['success', 'error', 'skipped'])->default('success');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewrite_logs');
    }
};
