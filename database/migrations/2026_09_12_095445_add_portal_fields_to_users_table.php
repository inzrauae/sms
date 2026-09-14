<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('uid')->unique()->after('id');
            $table->string('company')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('user')->after('password');
            $table->string('status')->default('active')->after('role');
            $table->integer('credits')->default(0)->after('status');
            $table->decimal('rate', 8, 2)->default(0.99)->after('credits');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uid', 'company', 'phone', 'role', 'status', 'credits', 'rate']);
        });
    }
};
