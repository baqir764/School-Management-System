<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * Phase 2 starter data (master spec section 49, seed data). Gives a fresh
 * install one active academic year and a basic Grade 1-6 structure with two
 * sections each, so Students/Teachers/Enrollment can be exercised
 * immediately without first hand-creating every class. Idempotent: skipped
 * entirely if an academic year already exists (e.g. re-running db:seed).
 */
class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        if (AcademicYear::query()->exists()) {
            return;
        }

        AcademicYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
            'is_active' => false,
        ]);

        AcademicYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $classNames = [
            1 => 'Grade 1', 2 => 'Grade 2', 3 => 'Grade 3',
            4 => 'Grade 4', 5 => 'Grade 5', 6 => 'Grade 6',
        ];

        foreach ($classNames as $level => $name) {
            $class = ClassRoom::create([
                'name' => $name,
                'numeric_level' => $level,
                'is_active' => true,
            ]);

            foreach (['A', 'B'] as $sectionName) {
                Section::create([
                    'class_id' => $class->id,
                    'name' => $sectionName,
                    'capacity' => 30,
                ]);
            }
        }
    }
}
