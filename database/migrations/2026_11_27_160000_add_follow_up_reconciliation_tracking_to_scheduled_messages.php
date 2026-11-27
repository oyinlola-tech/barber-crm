<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table): void {
            $table->timestamp('follow_up_reconciled_at')->nullable()->after('sent_at');
            $table->index(['status', 'follow_up_reconciled_at', 'id'], 'scheduled_messages_follow_up_recovery_index');
        });

        // Existing sent messages predate this recovery marker. Do not create
        // new follow-ups for historical traffic during deployment.
        DB::table('scheduled_messages')
            ->where('status', 'sent')
            ->update(['follow_up_reconciled_at' => DB::raw('COALESCE(sent_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table): void {
            $table->dropIndex('scheduled_messages_follow_up_recovery_index');
            $table->dropColumn('follow_up_reconciled_at');
        });
    }
};
