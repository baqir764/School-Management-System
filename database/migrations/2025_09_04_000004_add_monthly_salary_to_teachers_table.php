<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teachers can now be paid through the same payroll flow as
     * Employees (see salary_payments). Nullable: existing teacher
     * records predate payroll and simply have no salary set until an
     * admin fills it in.
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->decimal('monthly_salary', 10, 2)->nullable()->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('monthly_salary');
        });
    }
};
