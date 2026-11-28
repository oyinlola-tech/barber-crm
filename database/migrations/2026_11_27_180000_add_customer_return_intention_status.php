<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('intended_return_status', 16)->nullable()->after('intended_return_date');
            $table->index(['business_id', 'intended_return_status', 'intended_return_date'], 'customers_return_intention_status_idx');
        });

        DB::table('customers')->whereNotNull('intended_return_date')->update(['intended_return_status' => 'expected']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_return_intention_status_idx');
            $table->dropColumn('intended_return_status');
        });
    }
};
