<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('password_change_required')->default(false);
            $table->timestamp('temporary_password_expires_at')->nullable();
        });

        Schema::table('staff_invitations', function (Blueprint $table): void {
            $table->string('temporary_password_hash')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table): void {
            $table->dropColumn('temporary_password_hash');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['password_change_required', 'temporary_password_expires_at']);
        });
    }
};
