<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassRoom>
 */
class ClassRoomFactory extends Factory
{
    public function definition(): array
    {
        static $level = 0;
        $level++;

        return [
            'name' => 'Grade '.$level,
            'numeric_level' => $level,
            'is_active' => true,
        ];
    }
}
