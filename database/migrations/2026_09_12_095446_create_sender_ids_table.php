<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mask');
            $table->string('status')->default('pending');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'mask']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_ids');
    }
};
