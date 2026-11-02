<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeeInvoice>
 */
class FeeInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'fee_structure_id' => FeeStructure::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'amount' => fake()->numberBetween(500, 5000),
            'due_date' => null,
        ];
    }
}
