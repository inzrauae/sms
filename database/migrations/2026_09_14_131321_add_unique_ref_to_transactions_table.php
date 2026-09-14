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
        Schema::table('transactions', function (Blueprint $table) {
            // Stops a replayed PayPal capture (or any other provider
            // reference) from crediting the same payment twice. Nulls are
            // still unrestricted, so manual/bank-transfer rows are unaffected.
            $table->unique('ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['ref']);
        });
    }
};
