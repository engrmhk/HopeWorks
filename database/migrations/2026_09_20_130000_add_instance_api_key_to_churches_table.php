<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->text('instance_api_key')->nullable()->after('instance_api_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn('instance_api_key');
        });
    }
};
