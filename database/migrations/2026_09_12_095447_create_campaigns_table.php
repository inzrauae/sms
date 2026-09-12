<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('group_uid');
            $table->string('group_name')->nullable();
            $table->string('sender_id');
            $table->text('body');
            $table->string('sms_type')->default('plain');
            $table->integer('contacts')->default(0);
            $table->integer('units')->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('status')->default('Queued');
            $table->string('provider_ref')->nullable();
            $table->string('scheduled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
