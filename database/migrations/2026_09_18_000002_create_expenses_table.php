<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row = one non-salary expense (rent, utilities, maintenance,
     * supplies, etc.) - the "مصارف متفرقه" the Finance module tracks
     * alongside Payroll's salary_payments. expense_date mirrors
     * salary_payments.payment_date: a plain DATE column holding a Jalali
     * "YYYY-MM-DD" string (see App\Support\JalaliDate), never Carbon-cast.
     * Soft-deletes for the same audit-trail reason salary_payments has
     * them, even though no destroy() route is exposed yet.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('title', 150);
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'other'])->default('cash');
            $table->string('voucher_number')->unique();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
