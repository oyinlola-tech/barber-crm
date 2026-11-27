<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_events', function (Blueprint $table): void {
            $table->string('provider_message_id')->nullable();
            $table->index(['business_id', 'provider_message_id'], 'message_events_provider_message_idx');
        });
    }

    public function down(): void
    {
        Schema::table('message_events', function (Blueprint $table): void {
            $table->dropIndex('message_events_provider_message_idx');
            $table->dropColumn('provider_message_id');
        });
    }
};
