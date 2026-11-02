<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a discount/deduction amount to salary payments (payroll discount
     * capability). "amount" keeps meaning exactly what it always has - the
     * base/gross salary being paid for the month, pre-filled from
     * Teacher/Employee::monthly_salary on the pay form but still editable
     * for one-off adjustments; "discount_amount" is a new, separate
     * deduction (e.g. an unpaid-leave or advance deduction) subtracted
     * from it. The amount actually handed to the payee is never stored
     * redundantly - SalaryPayment::netAmount() computes
     * amount - discount_amount live, the same "derive, don't cache"
     * approach FeeInvoice::balance() already uses for fee payments.
     */
    public function up(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
