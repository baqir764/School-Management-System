<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 21 (Employees / Payroll). Non-teaching staff -
     * accountants, guards, cleaners, receptionists, etc. Deliberately a
     * basic HR record (unlike the fuller Teacher profile): just enough to
     * run payroll and know who's on staff. "monthly_salary" is this
     * employee's standing salary - SalaryPaymentController defaults a new
     * payment's amount to it, but each payment can still be adjusted for
     * a bonus/deduction without changing the standing figure.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique(); // e.g. STF-0001
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position');
            $table->string('phone', 30)->nullable();
            $table->string('national_id')->nullable();
            $table->decimal('monthly_salary', 10, 2);
            $table->date('joining_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
