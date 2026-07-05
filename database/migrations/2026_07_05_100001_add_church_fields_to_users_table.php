<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable()->after('id')->constrained('churches')->nullOnDelete();
            $table->text('totp_secret')->nullable()->after('password');
            $table->boolean('totp_enabled')->default(false)->after('totp_secret');
            $table->boolean('must_reset_password')->default(false)->after('totp_enabled');
            $table->timestamp('onboarding_completed_at')->nullable()->after('must_reset_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('church_id');
            $table->dropColumn(['totp_secret', 'totp_enabled', 'must_reset_password', 'onboarding_completed_at']);
        });
    }
};
