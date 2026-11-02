<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 20 (Fee Invoices). One row = "this student owes
     * this much for this fee head." amount is a snapshot of the
     * fee_structures.amount at generation time, so a later change to the
     * fee structure doesn't silently rewrite an invoice that was already
     * handed to a family. Whether it's been paid is never stored here -
     * FeeInvoice::balance()/status() compute it live from fee_payments,
     * the same "derive, don't cache" approach used throughout this app.
     */
    public function up(): void
    {
        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One invoice per student per fee head - re-running "generate"
            // for the same fee structure skips students who already have one.
            $table->unique(['student_id', 'fee_structure_id'], 'fee_invoices_student_structure_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_invoices');
    }
};
