<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Period>
 */
class PeriodFactory extends Factory
{
    public function definition(): array
    {
        static $n = 0;
        $n++;

        $start = sprintf('%02d:00', 7 + $n);
        $end = sprintf('%02d:45', 7 + $n);

        return [
            'name' => "Period {$n}",
            'start_time' => $start,
            'end_time' => $end,
            'sort_order' => $n,
        ];
    }
}
