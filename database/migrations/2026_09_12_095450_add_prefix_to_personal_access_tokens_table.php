<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Displaying a token in the tenant dashboard needs a few visible
     * characters; Sanctum only stores the hash, so a short prefix is kept
     * alongside it purely for that display, never used to authenticate.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('prefix', 6)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('prefix');
        });
    }
};
