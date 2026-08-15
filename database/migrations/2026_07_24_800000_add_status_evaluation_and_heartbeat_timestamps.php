<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('status_evaluated_at')->nullable()->after('grace_started_at');
        });

        Schema::table('churches', function (Blueprint $table): void {
            $table->timestamp('last_heartbeat_at')->nullable()->after('instance_api_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn('last_heartbeat_at');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('status_evaluated_at');
        });
    }
};
