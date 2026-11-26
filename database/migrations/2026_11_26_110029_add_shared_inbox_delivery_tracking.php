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
        Schema::table('messages', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('provider_error_code')->nullable();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('last_customer_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
        });

        Schema::create('conversation_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['business_id', 'conversation_id', 'created_at']);
        });

        Schema::create('message_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('webhook_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('status')->nullable();
            $table->string('provider_error_code')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->unique('webhook_event_id');
            $table->index(['business_id', 'message_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_events');
        Schema::dropIfExists('conversation_notes');

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['last_customer_message_at', 'unread_count']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn(['delivered_at', 'read_at', 'failed_at', 'provider_error_code']);
        });
    }
};
