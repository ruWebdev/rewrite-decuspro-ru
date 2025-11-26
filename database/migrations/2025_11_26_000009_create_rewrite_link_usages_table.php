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
        Schema::create('rewrite_link_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rewrite_link_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('article_joomla_id');
            $table->timestamps();

            $table->unique(['site_id', 'rewrite_link_id']);
            $table->index(['site_id', 'article_joomla_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewrite_link_usages');
    }
};
