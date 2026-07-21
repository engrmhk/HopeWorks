<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('stripe_price_id')->nullable()->after('module_eligibility');
        });

        Schema::table('churches', function (Blueprint $table): void {
            $table->string('contact_email')->nullable()->after('name');
            $table->string('billing_email')->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn(['contact_email', 'billing_email']);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('stripe_price_id');
        });
    }
};
