<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Section>
 */
class SectionFactory extends Factory
{
    /**
     * In-memory per-class name counter. Sections are unique per class
     * (class_id, name), so a factory that randomly reuses 'A'/'B' from a
     * fixed pool is the classic source of flaky "Duplicate entry" failures
     * whenever a test creates two sections in one class. Handing out the
     * next unused letter per class keeps batches deterministic instead.
     */
    protected static array $sectionNamesByClass = [];

    public function definition(): array
    {
        return [
            'class_id' => ClassRoom::factory(),
            'name' => function (array $attributes): string {
                $key = $attributes['class_id'] ?? null;
                // A nested ClassRoom factory resolves to its own row, so the
                // shared bucket can never collide across different classes.
                $key = is_object($key) ? '(nested)' : (string) $key;

                self::$sectionNamesByClass[$key] ??= 0;

                $letters = range('A', 'Z');
                $name = $letters[self::$sectionNamesByClass[$key] % count($letters)];
                self::$sectionNamesByClass[$key]++;

                return $name;
            },
            'capacity' => 30,
        ];
    }
}
