<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        // Defaults the admin console can change later under Settings.
        foreach (config('portal') as $key => $value) {
            if (in_array($key, ['brand_name', 'default_rate', 'signup_bonus', 'support_email', 'currency'], true)) {
                DB::table('settings')->insert(['key' => $key, 'value' => (string) $value]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
