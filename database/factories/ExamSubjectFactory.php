<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamSubject>
 */
class ExamSubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'subject_id' => Subject::factory(),
            'full_marks' => 100,
            'pass_marks' => 40,
        ];
    }
}
