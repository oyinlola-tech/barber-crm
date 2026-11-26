<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger');
            $table->string('action');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->boolean('is_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'is_enabled', 'trigger']);
        });

        Schema::create('scheduled_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('idempotency_key', 128);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['business_id', 'customer_id', 'status']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreign('scheduled_message_id')->references('id')->on('scheduled_messages')->nullOnDelete();
        });

        Schema::create('automation_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scheduled_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->json('context')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['scheduled_message_id']);
        });
        Schema::dropIfExists('automation_executions');
        Schema::dropIfExists('scheduled_messages');
        Schema::dropIfExists('automation_rules');
    }
};
