<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->unique()->constrained('churches')->cascadeOnDelete();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('theme_color')->nullable();
            $table->string('login_image')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('contact_info')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_branding');
    }
};
