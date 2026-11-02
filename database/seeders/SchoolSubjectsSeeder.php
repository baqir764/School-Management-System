<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SchoolSubjectsSeeder extends Seeder
{
    /**
     * The curriculum table the school provided: Grade 1-3 share this list.
     * Keyed by a short, stable code prefix (rather than deriving one from
     * the Dari name) so re-running this seeder - see run() - always finds
     * the same row by code even if a subject's display name is edited
     * later.
     */
    private const LOWER_GRADES_SUBJECTS = [
        'REL-H' => 'علوم دینی ح',
        'REL-J' => 'علوم دینی ج',
        'MATH' => 'ریاضی',
        'SKILL' => 'مهارت‌ها',
        'ART' => 'هنررسامی',
        'CALLIG' => 'حسن‌خط',
        'DARI' => 'دری',
        'QURAN' => 'قرآن‌کریم',
    ];

    /**
     * Grade 4-6: same core subjects as above, but مهارت‌ها is replaced by
     * اجتماعیات, plus three more (پشتو، ساینس، انګلیسی) that only start
     * from Grade 4 per the table.
     */
    private const UPPER_GRADES_SUBJECTS = [
        'REL-H' => 'علوم دینی ح',
        'REL-J' => 'علوم دینی ج',
        'MATH' => 'ریاضی',
        'SOC' => 'اجتماعیات',
        'ART' => 'هنررسامی',
        'CALLIG' => 'حسن‌خط',
        'DARI' => 'دری',
        'QURAN' => 'قرآن‌کریم',
        'PASHTO' => 'پشتو',
        'SCI' => 'ساینس',
        'ENG' => 'انګلیسی',
    ];

    /**
     * Class names to create if a class at that numeric_level doesn't
     * already exist - classes are no longer soft-deleted (see the
     * drop_soft_deletes migration), so a class that was previously deleted
     * needs to be recreated, not assumed present.
     */
    private const CLASS_NAMES = [
        1 => 'صنف اول',
        2 => 'صنف دوم',
        3 => 'صنف سوم',
        4 => 'صنف چهارم',
        5 => 'صنف پنجم',
        6 => 'صنف ششم',
    ];

    /**
     * Safe to run more than once: every class is looked up by
     * numeric_level (firstOrCreate, so an existing class is reused as-is
     * and never renamed), and every subject is looked up by its code
     * (updateOrCreate, so re-running this after tweaking a name/mark
     * above updates the existing row instead of creating a duplicate).
     */
    public function run(): void
    {
        foreach (self::CLASS_NAMES as $level => $className) {
            $class = ClassRoom::firstOrCreate(
                ['numeric_level' => $level],
                ['name' => $className, 'is_active' => true]
            );

            $subjects = $level <= 3 ? self::LOWER_GRADES_SUBJECTS : self::UPPER_GRADES_SUBJECTS;

            foreach ($subjects as $codePrefix => $subjectName) {
                Subject::updateOrCreate(
                    ['code' => "{$codePrefix}-{$level}"],
                    [
                        'name' => $subjectName,
                        'class_id' => $class->id,
                        'full_marks' => 100,
                        'pass_marks' => 40,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
