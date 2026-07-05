<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_cache', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('active');
            $table->string('enforcement_policy')->nullable();
            $table->text('signed_jwt')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_cache');
    }
};
