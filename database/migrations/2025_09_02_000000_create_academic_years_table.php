<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 6 (Academic Year Setup). Exactly one academic
     * year may be active at a time; enforced in the model/controller layer
     * (see AcademicYear::activate()), not by a DB constraint, since MySQL
     * has no native "unique where true" support without a generated column.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "1404-1405" or "2025-2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
