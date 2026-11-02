<?php

namespace Database\Factories;

use App\Models\ExamSubject;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mark>
 */
class MarkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_subject_id' => ExamSubject::factory(),
            'student_id' => Student::factory(),
            'marks_obtained' => fake()->numberBetween(30, 100),
            'is_absent' => false,
        ];
    }
}
