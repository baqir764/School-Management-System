<?php

namespace Database\Factories;

use App\Models\Period;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimetableEntry>
 */
class TimetableEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'period_id' => Period::factory(),
            'day_of_week' => fake()->numberBetween(1, 7),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
        ];
    }
}
