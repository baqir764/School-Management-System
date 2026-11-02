<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a per-invoice discount (fee discount capability - e.g. a
     * sibling or scholarship discount granted to one student on one fee
     * head). "amount" stays the untouched snapshot of the fee structure's
     * amount at generation time, exactly as already documented on the
     * fee_invoices table; discount_amount is a separate reduction applied
     * afterwards by fees.manage via FeeInvoiceController::update().
     * FeeInvoice::balance()/status() subtract it from amount before
     * comparing against payments, the same "derive, don't cache" approach
     * already used for paid/balance/status.
     */
    public function up(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
