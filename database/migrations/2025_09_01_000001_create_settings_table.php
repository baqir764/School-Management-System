<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * School Setup (master spec section 5). Deliberately a single-row
     * table: the application enforces there is exactly one settings
     * record (see App\Models\Setting::current()).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name')->default('School Management System');
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('principal_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('currency', 3)->default('AFN');
            $table->string('timezone', 64)->default('Asia/Kabul');
            $table->string('default_locale', 5)->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
