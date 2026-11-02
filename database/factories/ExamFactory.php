<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exam>
 */
class ExamFactory extends Factory
{
    public function definition(): array
    {
        $today = JalaliDate::today();

        return [
            'academic_year_id' => AcademicYear::factory(),
            'class_id' => ClassRoom::factory(),
            'name' => fake()->unique()->words(2, true).' Exam',
            'term_type' => 'final',
            // Shamsi "YYYY-MM-DD" strings (see App\Support\JalaliDate),
            // not Carbon instances - these columns have no 'date' cast.
            'start_date' => JalaliDate::addDays($today, 7),
            'end_date' => JalaliDate::addDays($today, 14),
            'is_published' => false,
        ];
    }
}
