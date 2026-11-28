<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_consents', function (Blueprint $table): void {
            $table->string('purpose', 80)->nullable()->after('channel');
            $table->string('evidence_reference', 255)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('customer_consents', function (Blueprint $table): void {
            $table->dropColumn(['purpose', 'evidence_reference']);
        });
    }
};
