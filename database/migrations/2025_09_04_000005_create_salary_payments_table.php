<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 21 (Payroll). One row = one salary payment to
     * either a Teacher or an Employee, via a polymorphic "payee"
     * (payee_type/payee_id - see the morph map registered in
     * AppServiceProvider, which stores 'teacher'/'employee' rather than
     * full class names). "salary_month" is normalized to the 1st of the
     * month it covers, and the unique constraint below means a given
     * payee can only be paid once per month - re-opening a month means
     * correcting/deleting that one row, not stacking another payment on
     * top of it.
     */
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payee_type');
            $table->unsignedBigInteger('payee_id');
            $table->decimal('amount', 10, 2);
            $table->date('salary_month');
            $table->date('payment_date');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'other'])->default('cash');
            $table->string('voucher_number')->unique();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payee_type', 'payee_id']);
            $table->unique(['payee_type', 'payee_id', 'salary_month'], 'salary_payments_payee_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
