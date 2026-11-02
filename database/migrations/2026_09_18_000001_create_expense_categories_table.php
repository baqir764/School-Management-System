<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Non-salary expense categories (e.g. "Utilities", "Maintenance",
     * "Supplies") - master data only, like Period/Shift, so no
     * soft-deletes: a category in use by an Expense row is guarded against
     * deletion in ExpenseCategoryController instead (same pattern as
     * ShiftController).
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
