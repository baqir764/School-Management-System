<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Section;
use App\Models\Student;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'class_id' => ClassRoom::factory(),
            'section_id' => Section::factory(),
            // A Shamsi "YYYY-MM-DD" string (see App\Support\JalaliDate),
            // not a Carbon instance - this column has no 'date' cast.
            'enrollment_date' => JalaliDate::today(),
            'status' => 'active',
        ];
    }
}
