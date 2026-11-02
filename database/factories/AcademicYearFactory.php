<?php

namespace Database\Factories;

use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');
        $end = (clone $start)->modify('+9 months');

        return [
            'name' => fake()->unique()->numerify('####-####'),
            // start_date/end_date are Shamsi "YYYY-MM-DD" strings (see
            // App\Support\JalaliDate) - fake Gregorian dates and convert them.
            'start_date' => JalaliDate::fromGregorian($start),
            'end_date' => JalaliDate::fromGregorian($end),
            'is_active' => false,
        ];
    }
}
