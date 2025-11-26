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
        Schema::create('rewrite_links', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('domain');
            $table->string('anchor')->nullable();
            $table->timestamps();

            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewrite_links');
    }
};
