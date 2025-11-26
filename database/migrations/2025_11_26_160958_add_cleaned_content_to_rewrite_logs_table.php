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
            $table->longText('cleaned_content')->nullable()->after('original_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rewrite_logs', function (Blueprint $table) {
            $table->dropColumn('cleaned_content');
        });
    }
};
