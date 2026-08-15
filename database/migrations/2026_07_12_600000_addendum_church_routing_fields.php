<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->string('subdomain')->nullable()->change();
        });

        Schema::table('synods', function (Blueprint $table): void {
            $table->string('shared_subdomain')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('synods', function (Blueprint $table): void {
            $table->dropColumn('shared_subdomain');
        });
    }
};
