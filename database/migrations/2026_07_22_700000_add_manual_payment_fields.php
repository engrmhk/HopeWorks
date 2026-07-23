<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('source')->default('stripe')->after('status');
            $table->string('payment_method')->nullable()->after('source');
            $table->string('reference_note')->nullable()->after('payment_method');
            $table->foreignId('recorded_by')->nullable()->after('reference_note')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->json('permissions')->nullable()->after('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_super_admin', 'permissions']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['source', 'payment_method', 'reference_note']);
        });
    }
};
