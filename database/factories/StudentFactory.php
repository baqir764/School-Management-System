<?php

namespace Database\Factories;

use App\Models\Student;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Sequence number used by the admission_number definition below.
     *
     * It must NOT come straight from Student::nextAdmissionNumber(): Laravel
     * resolves the definition of every instance in a batch (e.g. count(3))
     * before inserting the first, so each would query the same empty table
     * and all grab the same number. This counter is seeded lazily from the
     * current max once per process, then incremented per instance, which
     * keeps them sequential AND never re-issues a number the app has
     * already handed out (the app derives from the table's real max).
     */
    protected static ?int $sequenceNumber = null;

    public function definition(): array
    {
        return [
            'admission_number' => function (): string {
                // Same year rule as the app itself: the active academic
                // year's start-date year, falling back to today's Shamsi
                // year when no year is active yet (see
                // Student::admissionNumberYear() - only the year helper is
                // called here, never nextAdmissionNumber(), for the batch
                // reason documented on $sequenceNumber above).
                $year = Student::admissionNumberYear();

                if (self::$sequenceNumber === null) {
                    self::$sequenceNumber = Student::withTrashed()
                        ->where('admission_number', 'like', "STU-{$year}-%")
                        ->orderByDesc('id')
                        ->value('admission_number');

                    self::$sequenceNumber = self::$sequenceNumber
                        ? ((int) substr(self::$sequenceNumber, strlen("STU-{$year}-"))) + 1
                        : 1;
                }

                $number = self::$sequenceNumber;
                self::$sequenceNumber++;

                return "STU-{$year}-".str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            },
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            // admission_date is a Shamsi "YYYY-MM-DD" string (see
            // App\Support\JalaliDate) - fake a Gregorian date and convert it,
            // same as every other date this factory would otherwise fake.
            'admission_date' => JalaliDate::fromGregorian(fake()->dateTimeBetween('-2 years', 'now')),
            'status' => 'active',
        ];
    }
}
