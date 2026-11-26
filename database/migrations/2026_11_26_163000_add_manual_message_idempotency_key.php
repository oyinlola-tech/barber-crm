<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('idempotency_key', 36)->nullable();
            $table->unique(['business_id', 'idempotency_key'], 'messages_business_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique('messages_business_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
