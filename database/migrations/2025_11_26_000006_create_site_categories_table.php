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
        Schema::create('site_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('joomla_id');
            $table->string('title');
            $table->string('alias')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'joomla_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_categories');
    }
};
