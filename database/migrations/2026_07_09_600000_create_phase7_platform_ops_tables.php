<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('payment_gateway_subscription_id')->nullable()->after('payment_gateway_customer_id');
            $table->timestamp('dunning_started_at')->nullable()->after('grace_started_at');
            $table->unsignedTinyInteger('dunning_attempt_count')->default(0)->after('dunning_started_at');
            $table->timestamp('dunning_exhausted_at')->nullable()->after('dunning_attempt_count');
            $table->foreignId('pending_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            $table->timestamp('pending_plan_effective_at')->nullable()->after('pending_plan_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('gateway_invoice_id')->nullable()->after('subscription_id');
            $table->string('pdf_path')->nullable()->after('paid_at');
        });

        Schema::table('control_plane_audit_logs', function (Blueprint $table): void {
            $table->boolean('high_visibility')->default(false)->after('action');
            $table->string('correlation_id')->nullable()->after('high_visibility');
        });

        Schema::create('client_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->unsignedInteger('active_user_count')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->json('enabled_modules')->nullable();
            $table->string('app_version')->nullable();
            $table->string('app_tag')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'created_at']);
        });

        Schema::create('impersonation_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('target_user_id');
            $table->foreignId('issued_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('correlation_id');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::table('tenant_export_jobs', function (Blueprint $table): void {
            $table->string('correlation_id')->nullable()->after('status');
            $table->json('metadata')->nullable()->after('file_path');
        });

        Schema::table('churches', function (Blueprint $table): void {
            $table->string('data_retention_status')->nullable()->after('instance_api_key_hash');
            $table->timestamp('data_retention_until')->nullable()->after('data_retention_status');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn(['data_retention_status', 'data_retention_until']);
        });

        Schema::table('tenant_export_jobs', function (Blueprint $table): void {
            $table->dropColumn(['correlation_id', 'metadata']);
        });

        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('impersonation_tokens');
        Schema::dropIfExists('client_health_snapshots');

        Schema::table('control_plane_audit_logs', function (Blueprint $table): void {
            $table->dropColumn(['high_visibility', 'correlation_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['gateway_invoice_id', 'pdf_path']);
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pending_plan_id');
            $table->dropColumn([
                'payment_gateway_subscription_id',
                'dunning_started_at',
                'dunning_attempt_count',
                'dunning_exhausted_at',
                'pending_plan_effective_at',
            ]);
        });
    }
};
