<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('provider_uid')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient');
            $table->string('sender_id');
            $table->text('body');
            $table->string('sms_type')->default('plain');
            $table->integer('segments')->default(1);
            $table->integer('units')->default(1);
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('status')->default('Queued');
            $table->string('source')->default('dashboard');
            $table->string('error')->nullable();
            $table->string('scheduled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
