<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('status')->default('active');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('synod_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('subdomain')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('instance_url')->nullable();
            $table->string('instance_api_key_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('church_affiliation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('synod_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('billing_interval')->default('monthly');
            $table->unsignedInteger('storage_limit_gb')->default(10);
            $table->unsignedInteger('user_limit')->default(25);
            $table->json('module_eligibility')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('synod_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->unsignedInteger('grace_period_days')->default(7);
            $table->string('enforcement_policy')->default('banner_only');
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_started_at')->nullable();
            $table->string('payment_gateway_customer_id')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'status']);
            $table->index(['synod_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->json('gateway_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('synod_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('signed_jwt');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('control_plane_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_plane_audit_logs');
        Schema::dropIfExists('tenant_export_jobs');
        Schema::dropIfExists('license_keys');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('church_affiliation_history');
        Schema::dropIfExists('churches');
        Schema::dropIfExists('synods');
    }
};
