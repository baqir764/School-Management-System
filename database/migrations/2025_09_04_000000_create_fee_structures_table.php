<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 19 (Fee Structure). Defines what should be
     * charged - not who has paid what, which is a later phase's invoices/
     * payments tables. Each row is one fee head (e.g. "Tuition Fee",
     * "Admission Fee") for one academic year, optionally scoped to a
     * single class - a null class_id means it applies school-wide across
     * every class for that year.
     */
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->string('name'); // e.g. "Tuition Fee", "Admission Fee", "Transport Fee"
            $table->decimal('amount', 10, 2);
            $table->enum('frequency', ['one_time', 'monthly', 'term', 'annual'])->default('term');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // The same fee head name can't be defined twice for the same
            // year/class combination (including the school-wide null slot).
            $table->unique(['academic_year_id', 'class_id', 'name'], 'fee_structures_year_class_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
