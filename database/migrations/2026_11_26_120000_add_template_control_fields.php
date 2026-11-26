<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('message_templates', 'purpose')) {
                $table->string('purpose')->nullable();
            }

            if (! Schema::hasColumn('message_templates', 'is_enabled')) {
                $table->boolean('is_enabled')->default(false);
            }

            if (! Schema::hasColumn('message_templates', 'provider_status')) {
                $table->string('provider_status')->nullable();
            }

            if (! Schema::hasColumn('message_templates', 'variable_map')) {
                $table->json('variable_map')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('message_templates', 'purpose')) {
                $table->dropColumn('purpose');
            }

            if (Schema::hasColumn('message_templates', 'is_enabled')) {
                $table->dropColumn('is_enabled');
            }

            if (Schema::hasColumn('message_templates', 'provider_status')) {
                $table->dropColumn('provider_status');
            }

            if (Schema::hasColumn('message_templates', 'variable_map')) {
                $table->dropColumn('variable_map');
            }
        });
    }
};
