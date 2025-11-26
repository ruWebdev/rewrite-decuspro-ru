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
        Schema::table('rewrite_logs', function (Blueprint $table) {
            $table->longText('original_content')->nullable()->after('message');
            $table->longText('rewritten_content')->nullable()->after('original_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rewrite_logs', function (Blueprint $table) {
            $table->dropColumn(['original_content', 'rewritten_content']);
        });
    }
};
