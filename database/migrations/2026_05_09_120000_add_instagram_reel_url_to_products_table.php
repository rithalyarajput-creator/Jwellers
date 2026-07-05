<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Optional Instagram reel URL surfaced on the storefront PDP.
            // Nullable — most products won't have one. 500 chars accommodates
            // long IG URLs with query strings (igsh tracking, etc).
            // No ->after() clause: column ordering doesn't affect behavior,
            // and pinning to a specific neighbor creates a brittle dependency
            // on whatever uncommitted migrations may or may not have added
            // intermediate columns (e.g. `keywords`, which is local-only).
            $table->string('instagram_reel_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('instagram_reel_url');
        });
    }
};
