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
        Schema::table('sender_ids', function (Blueprint $table) {
            $table->unsignedInteger('fee_units')->default(0)->after('note');
            $table->decimal('fee_amount', 10, 2)->default(0)->after('fee_units');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sender_ids', function (Blueprint $table) {
            $table->dropColumn(['fee_units', 'fee_amount']);
        });
    }
};
