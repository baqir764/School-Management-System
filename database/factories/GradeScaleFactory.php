<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradeScale>
 */
class GradeScaleFactory extends Factory
{
    public function definition(): array
    {
        static $n = 0;
        $n++;

        return [
            'grade_name' => 'G'.$n,
            'min_percentage' => 0,
            'max_percentage' => 100,
            'sort_order' => $n,
        ];
    }
}
