<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 10);
            $table->string('priority', 10);
            $table->string('status', 20)->default('queued');
            $table->string('recipient_id');
            $table->text('content');
            $table->string('idempotency_key')->unique();
            $table->string('gateway_id')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('idempotency_key');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
