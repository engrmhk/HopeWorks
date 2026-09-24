<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('synods', function (Blueprint $table): void {
            $table->string('instance_url')->nullable()->after('shared_subdomain');
            $table->string('instance_api_key_hash')->nullable()->after('instance_url');
            $table->text('instance_api_key')->nullable()->after('instance_api_key_hash');
            $table->timestamp('last_heartbeat_at')->nullable()->after('instance_api_key');
            $table->string('enforcement_policy')->nullable()->after('status');
            $table->text('platform_notice')->nullable()->after('enforcement_policy');
            $table->string('notice_severity')->nullable()->after('platform_notice');
        });

        Schema::table('churches', function (Blueprint $table): void {
            $table->boolean('is_synod_host')->default(false)->after('synod_id');
        });
    }

    public function down(): void
    {
        Schema::table('synods', function (Blueprint $table): void {
            $table->dropColumn([
                'instance_url',
                'instance_api_key_hash',
                'instance_api_key',
                'last_heartbeat_at',
                'enforcement_policy',
                'platform_notice',
                'notice_severity',
            ]);
        });

        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn('is_synod_host');
        });
    }
};
