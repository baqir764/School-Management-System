<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row = one miscellaneous (non-fee) receipt - "دریافت‌های متفرقه"
     * such as a donation, a grant, or hall rental income - kept separate
     * from fee_payments since it has nothing to do with a student invoice.
     * received_date mirrors fee_payments.payment_date: a plain DATE column
     * holding a Jalali "YYYY-MM-DD" string, never Carbon-cast.
     */
    public function up(): void
    {
        Schema::create('misc_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_category_id')->constrained('income_categories')->restrictOnDelete();
            $table->string('title', 150);
            $table->decimal('amount', 10, 2);
            $table->date('received_date');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'other'])->default('cash');
            $table->string('receipt_number')->unique();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('received_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('misc_incomes');
    }
};
