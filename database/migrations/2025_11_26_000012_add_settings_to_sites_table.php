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
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('skip_external_links')->default(true)->after('url');
            $table->text('allowed_tags')->nullable()->after('skip_external_links');
            $table->text('allowed_attributes')->nullable()->after('allowed_tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['skip_external_links', 'allowed_tags', 'allowed_attributes']);
        });
    }
};
