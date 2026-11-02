<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'section_id' => Section::factory(),
            'academic_year_id' => AcademicYear::factory(),
            // A Shamsi "YYYY-MM-DD" string (see App\Support\JalaliDate),
            // not a Carbon instance - this column has no 'date' cast.
            'date' => JalaliDate::today(),
            'status' => 'present',
        ];
    }
}
