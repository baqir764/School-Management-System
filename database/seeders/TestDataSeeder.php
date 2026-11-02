<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\GradeScale;
use App\Models\IncomeCategory;
use App\Models\Mark;
use App\Models\MiscIncome;
use App\Models\ParentGuardian;
use App\Models\Period;
use App\Models\SalaryPayment;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Support\JalaliDate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Fills every module of the app with enough Dari-named sample data to
 * exercise it end-to-end: teachers, sections, students, parents,
 * enrollments, a timetable, attendance history, an exam with marks, fee
 * structures/invoices/payments, non-teaching staff and payroll, and a
 * handful of linked login accounts.
 *
 * Deliberately NOT registered in DatabaseSeeder's default run() (same
 * treatment as SchoolSubjectsSeeder) - this is bulk demo/test data, not
 * something a fresh production install should get automatically. Run it
 * on its own:
 *
 *   php artisan db:seed --class=TestDataSeeder
 *
 * Safe to re-run: every step either checks a volume threshold first or
 * uses firstOrCreate/updateOrCreate on the row's natural unique key, so
 * running it twice does not double every table's data. The one exception
 * is attendance, which intentionally adds a few more days of history each
 * time it's re-run on a later date - that's a feature, not a bug, for a
 * seeder whose job is giving the Attendance module something to show.
 */
class TestDataSeeder extends Seeder
{
    private const STUDENTS_PER_SECTION = 8;

    private const MALE_FIRST_NAMES = [
        'احمد', 'محمد', 'علی', 'حامد', 'نجیب', 'ذبیح‌الله', 'عبدالله', 'رضا', 'کریم', 'یوسف',
        'ایوب', 'بلال', 'حمید', 'جاوید', 'ظاهر', 'مسعود', 'وحید', 'نصیر', 'فرید', 'شاه‌ولی',
    ];

    private const FEMALE_FIRST_NAMES = [
        'فاطمه', 'زهرا', 'مریم', 'نرگس', 'ساره', 'حدیثه', 'بصیره', 'لیلا', 'سمیرا', 'فرشته',
        'نیلوفر', 'پروین', 'آمنه', 'رابعه', 'شکریه', 'وحیده', 'حمیرا', 'تمنا', 'ملالی', 'گلثوم',
    ];

    private const FAMILY_NAMES = [
        'احمدی', 'حسینی', 'کریمی', 'رضایی', 'نجفی', 'یوسفی', 'صدیقی', 'نوری', 'رحیمی', 'سروری',
        'هاشمی', 'سعیدی', 'ولی‌زاده', 'محمدی', 'عظیمی', 'قاسمی', 'سلطانی', 'رستمی', 'ابراهیمی', 'ناصری',
    ];

    private const PROVINCES = [
        'کابل', 'هرات', 'بلخ', 'ننگرهار', 'قندهار', 'بامیان', 'بدخشان', 'پروان', 'غزنی', 'کندز',
    ];

    public function run(): void
    {
        // Only seed the curriculum if it isn't there yet - this seeder must
        // never silently overwrite a subject an admin has since edited from
        // the Subjects screen (SchoolSubjectsSeeder's updateOrCreate would).
        if (Subject::query()->doesntExist()) {
            $this->call(SchoolSubjectsSeeder::class);
        }

        $year = $this->ensureActiveAcademicYear();
        $this->seedGradeScale();
        $periods = $this->seedPeriods();
        $teachers = $this->seedTeachers();
        $this->assignSubjectTeachers($teachers);
        $sections = $this->seedSections($teachers);
        $employees = $this->seedEmployees();
        $this->seedFeeStructures($year);

        [$students, $enrollments] = $this->seedStudentsAndEnrollments($year, $sections);
        $parents = $this->seedParents($students);

        $this->seedTimetable($sections, $periods, $teachers);
        $this->seedAttendance($year, $enrollments);
        $this->seedExamsAndMarks($year, $enrollments);
        $this->seedFeeInvoicesAndPayments($year, $enrollments);
        $this->seedSalaryPayments($teachers, $employees);
        $this->seedExpensesAndMiscIncomes();
        [$linkedUsers, $parentSender] = $this->linkSampleLoginAccounts($teachers, $students, $parents);
        $this->seedStudentDocument($students);
        $this->seedAnnouncements($linkedUsers, $parentSender);
    }

    private function pick(array $pool, int $index): string
    {
        return $pool[$index % count($pool)];
    }

    private function ensureActiveAcademicYear(): AcademicYear
    {
        if ($year = AcademicYear::current()) {
            return $year;
        }

        if ($year = AcademicYear::query()->first()) {
            $year->activate();

            return $year->fresh();
        }

        [$currentJalaliYear] = JalaliDate::parts(JalaliDate::today());

        return AcademicYear::create([
            'name' => "{$currentJalaliYear}-".($currentJalaliYear + 1),
            'start_date' => JalaliDate::startOfMonth($currentJalaliYear, 1),
            'end_date' => JalaliDate::endOfMonth($currentJalaliYear, 12),
            'is_active' => true,
        ]);
    }

    private function seedGradeScale(): void
    {
        if (GradeScale::query()->exists()) {
            return;
        }

        $bands = [
            ['A+', 90, 100, 'عالی'],
            ['A', 80, 89.99, 'خیلی خوب'],
            ['B+', 70, 79.99, 'خوب'],
            ['B', 60, 69.99, 'متوسط بالا'],
            ['C', 50, 59.99, 'متوسط'],
            ['D', 40, 49.99, 'کافی'],
            ['F', 0, 39.99, 'ناکام'],
        ];

        foreach ($bands as $i => [$name, $min, $max, $description]) {
            GradeScale::create([
                'grade_name' => $name,
                'min_percentage' => $min,
                'max_percentage' => $max,
                'description' => $description,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function seedPeriods()
    {
        if (Period::query()->exists()) {
            return Period::query()->ordered()->get();
        }

        $slots = [
            ['زنگ اول', '07:30', '08:10'],
            ['زنگ دوم', '08:10', '08:50'],
            ['زنگ سوم', '08:50', '09:30'],
            ['زنگ چهارم', '09:50', '10:30'],
            ['زنگ پنجم', '10:30', '11:10'],
            ['زنگ ششم', '11:10', '11:50'],
        ];

        return collect($slots)->map(fn ($slot, $i) => Period::create([
            'name' => $slot[0],
            'start_time' => $slot[1],
            'end_time' => $slot[2],
            'sort_order' => $i + 1,
        ]));
    }

    private function seedTeachers()
    {
        if (Teacher::query()->count() >= 10) {
            return Teacher::query()->get();
        }

        $qualifications = ['لیسانس تعلیم و تربیه', 'لیسانس ادبیات', 'لیسانس ریاضی', 'لیسانس ساینس', 'فوق لیسانس تعلیمات اسلامی', 'فارغ دارالمعلمین'];
        $specializations = ['ریاضی', 'دری', 'پشتو', 'علوم دینی', 'ساینس', 'اجتماعیات', 'انګلیسی', 'هنر و مهارت‌ها'];

        $teachers = collect();

        for ($i = 0; $i < 16; $i++) {
            $isFemale = $i % 3 === 0;
            $first = $isFemale ? $this->pick(self::FEMALE_FIRST_NAMES, $i) : $this->pick(self::MALE_FIRST_NAMES, $i);

            $teachers->push(Teacher::create([
                'employee_code' => Teacher::nextEmployeeCode(),
                'first_name' => $first,
                'last_name' => $this->pick(self::FAMILY_NAMES, $i + 5),
                'father_name' => $this->pick(self::MALE_FIRST_NAMES, $i + 3),
                'gender' => $isFemale ? 'female' : 'male',
                'date_of_birth' => JalaliDate::fromGregorian(now()->subYears(random_int(25, 50))->subDays(random_int(0, 300))),
                'phone' => '07'.random_int(10000000, 99999999),
                'address' => 'ناحیه '.random_int(1, 15).'، شهر '.$this->pick(self::PROVINCES, $i),
                'qualification' => $this->pick($qualifications, $i),
                'specialization' => $this->pick($specializations, $i),
                'joining_date' => JalaliDate::fromGregorian(now()->subYears(random_int(1, 6))),
                'employment_type' => $i % 5 === 0 ? 'contract' : 'full_time',
                'monthly_salary' => random_int(6, 15) * 1000,
                'status' => 'active',
            ]));
        }

        return $teachers;
    }

    /**
     * Fills in teacher_id for any subject that doesn't already have one -
     * never overwrites a subject an admin already assigned a teacher to.
     */
    private function assignSubjectTeachers($teachers): void
    {
        $subjects = Subject::query()->whereNull('teacher_id')->orderBy('id')->get();

        foreach ($subjects as $i => $subject) {
            $subject->update(['teacher_id' => $teachers[$i % $teachers->count()]->id]);
        }
    }

    private function seedSections($teachers)
    {
        $sections = collect();
        $teacherIndex = 0;

        foreach (ClassRoom::query()->ordered()->get() as $class) {
            // Reuse whatever sections already exist for the class (e.g. the
            // A/B pair AcademicStructureSeeder creates) so we never end up
            // with duplicate parallel sections - only mint الف/ب when the
            // class genuinely has none yet.
            $classSections = Section::query()->where('class_id', $class->id)->orderBy('id')->get();

            if ($classSections->isEmpty()) {
                foreach (['الف', 'ب'] as $sectionName) {
                    $classSections->push(Section::create([
                        'class_id' => $class->id,
                        'name' => $sectionName,
                        'capacity' => 25,
                        'room_number' => (string) ($class->numeric_level * 10 + ($sectionName === 'الف' ? 1 : 2)),
                    ]));
                }
            }

            foreach ($classSections as $section) {
                if (! $section->class_teacher_id) {
                    $section->update(['class_teacher_id' => $teachers[$teacherIndex % $teachers->count()]->id]);
                }

                $teacherIndex++;
                $sections->push($section);
            }
        }

        return $sections;
    }

    private function seedEmployees()
    {
        if (Employee::query()->count() >= 5) {
            return Employee::query()->get();
        }

        $roles = [
            ['محاسب', 'رحیمی', false],
            ['محافظ', 'یوسفی', false],
            ['نظافتچی', 'کریمی', true],
            ['مدیر دفتر', 'حسینی', true],
            ['راننده', 'نجفی', false],
        ];

        $employees = collect();

        foreach ($roles as $i => [$position, $lastName, $isFemale]) {
            $first = $isFemale ? $this->pick(self::FEMALE_FIRST_NAMES, $i + 10) : $this->pick(self::MALE_FIRST_NAMES, $i + 10);

            $employees->push(Employee::create([
                'employee_code' => Employee::nextEmployeeCode(),
                'first_name' => $first,
                'last_name' => $lastName,
                'position' => $position,
                'phone' => '07'.random_int(10000000, 99999999),
                'monthly_salary' => random_int(4, 9) * 1000,
                'joining_date' => JalaliDate::fromGregorian(now()->subYears(random_int(1, 5))),
                'status' => 'active',
            ]));
        }

        return $employees;
    }

    private function seedFeeStructures(AcademicYear $year): void
    {
        FeeStructure::firstOrCreate(
            ['academic_year_id' => $year->id, 'class_id' => null, 'name' => 'حق الثبت نام'],
            ['amount' => 500, 'frequency' => 'one_time', 'is_active' => true, 'description' => 'فیس یکبارگی ثبت نام دانش‌آموز جدید']
        );

        FeeStructure::firstOrCreate(
            ['academic_year_id' => $year->id, 'class_id' => null, 'name' => 'فیس امتحان'],
            ['amount' => 200, 'frequency' => 'term', 'is_active' => true, 'description' => 'فیس امتحانات چهارماهه']
        );

        foreach (ClassRoom::query()->ordered()->get() as $class) {
            FeeStructure::firstOrCreate(
                ['academic_year_id' => $year->id, 'class_id' => $class->id, 'name' => 'شهریه ماهوار'],
                [
                    'amount' => 300 + ($class->numeric_level * 50),
                    'frequency' => 'monthly',
                    'is_active' => true,
                    'description' => "شهریه ماهانه {$class->name}",
                ]
            );
        }
    }

    private function seedStudentsAndEnrollments(AcademicYear $year, $sections): array
    {
        $existing = Enrollment::query()->where('academic_year_id', $year->id)->count();

        if ($existing >= $sections->count() * self::STUDENTS_PER_SECTION) {
            $enrollments = Enrollment::query()->where('academic_year_id', $year->id)->get();
            $students = Student::query()->whereIn('id', $enrollments->pluck('student_id'))->get();

            return [$students, $enrollments];
        }

        $numericLevelByClassId = ClassRoom::query()->pluck('numeric_level', 'id');

        $students = collect();
        $enrollments = collect();
        $globalIndex = 0;

        foreach ($sections as $section) {
            $numericLevel = $numericLevelByClassId[$section->class_id] ?? 1;

            for ($n = 1; $n <= self::STUDENTS_PER_SECTION; $n++) {
                $isFemale = $globalIndex % 2 === 0;
                $first = $isFemale ? $this->pick(self::FEMALE_FIRST_NAMES, $globalIndex) : $this->pick(self::MALE_FIRST_NAMES, $globalIndex);
                $ageYears = 6 + $numericLevel;

                $student = Student::create([
                    'admission_number' => Student::nextAdmissionNumber(),
                    'first_name' => $first,
                    'last_name' => $this->pick(self::FAMILY_NAMES, $globalIndex + 2),
                    'father_name' => $this->pick(self::MALE_FIRST_NAMES, $globalIndex + 4),
                    'grandfather_name' => $this->pick(self::MALE_FIRST_NAMES, $globalIndex + 8),
                    'gender' => $isFemale ? 'female' : 'male',
                    'date_of_birth' => JalaliDate::fromGregorian(now()->subYears($ageYears)->subDays(random_int(0, 300))),
                    'place_of_birth' => $this->pick(self::PROVINCES, $globalIndex),
                    'nationality' => 'افغان',
                    'religion' => 'اسلام',
                    'address' => 'ناحیه '.random_int(1, 15).'، '.$this->pick(self::PROVINCES, $globalIndex + 1),
                    'province' => $this->pick(self::PROVINCES, $globalIndex + 1),
                    'district' => 'ولسوالی مرکزی',
                    'phone' => '07'.random_int(10000000, 99999999),
                    'admission_date' => JalaliDate::fromGregorian(now()->subMonths(random_int(1, 24))),
                    'status' => 'active',
                ]);

                $enrollment = Enrollment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'class_id' => $section->class_id,
                    'section_id' => $section->id,
                    'roll_number' => (string) $n,
                    'enrollment_date' => JalaliDate::today(),
                    'status' => 'active',
                ]);

                $students->push($student);
                $enrollments->push($enrollment);
                $globalIndex++;
            }
        }

        return [$students, $enrollments];
    }

    private function seedParents($students)
    {
        $occupationsMale = ['دکاندار', 'دهقان', 'کارمند دولت', 'راننده', 'معلم', 'تاجر'];
        $occupationsFemale = ['خانه‌دار', 'معلم', 'کارمند صحی'];

        $created = collect();
        $index = 0;

        foreach ($students as $student) {
            if ($student->parents()->exists()) {
                $index++;

                continue;
            }

            $father = ParentGuardian::create([
                'first_name' => $this->pick(self::MALE_FIRST_NAMES, $index + 6),
                'last_name' => $student->last_name,
                'occupation' => $this->pick($occupationsMale, $index),
                'phone' => '07'.random_int(10000000, 99999999),
                'address' => $student->address,
            ]);
            $student->parents()->attach($father->id, ['relationship' => 'Father', 'is_primary_contact' => true]);
            $created->push($father);

            if ($index % 2 === 0) {
                $mother = ParentGuardian::create([
                    'first_name' => $this->pick(self::FEMALE_FIRST_NAMES, $index + 6),
                    'last_name' => $student->last_name,
                    'occupation' => $this->pick($occupationsFemale, $index),
                    'phone' => '07'.random_int(10000000, 99999999),
                    'address' => $student->address,
                ]);
                $student->parents()->attach($mother->id, ['relationship' => 'Mother', 'is_primary_contact' => false]);
                $created->push($mother);
            }

            $index++;
        }

        return $created;
    }

    private function seedTimetable($sections, $periods, $teachers): void
    {
        $subjectsByClass = Subject::query()->get()->groupBy('class_id')->map->values();
        $days = [1, 2, 3, 4, 5]; // Monday - Friday
        $periodsToUse = $periods->take(4);

        foreach ($sections as $section) {
            $subjects = $subjectsByClass->get($section->class_id, collect());

            if ($subjects->isEmpty()) {
                continue;
            }

            $subjectIndex = 0;

            foreach ($days as $day) {
                foreach ($periodsToUse as $period) {
                    $subject = $subjects[$subjectIndex % $subjects->count()];
                    $teacherId = $subject->teacher_id ?: $teachers[$subjectIndex % $teachers->count()]->id;

                    TimetableEntry::firstOrCreate(
                        ['section_id' => $section->id, 'period_id' => $period->id, 'day_of_week' => $day],
                        ['subject_id' => $subject->id, 'teacher_id' => $teacherId]
                    );

                    $subjectIndex++;
                }
            }
        }
    }

    private function seedAttendance(AcademicYear $year, $enrollments): void
    {
        $statuses = ['present', 'present', 'present', 'present', 'absent', 'late', 'excused'];
        $markedBy = User::query()->where('email', 'teacher@school.local')->value('id');
        $today = JalaliDate::today();

        foreach ($enrollments as $enrollment) {
            for ($d = 1; $d <= 4; $d++) {
                $date = JalaliDate::addDays($today, -$d);

                AttendanceRecord::firstOrCreate(
                    ['student_id' => $enrollment->student_id, 'date' => $date],
                    [
                        'section_id' => $enrollment->section_id,
                        'academic_year_id' => $year->id,
                        'status' => $statuses[array_rand($statuses)],
                        'marked_by' => $markedBy,
                    ]
                );
            }
        }
    }

    private function seedExamsAndMarks(AcademicYear $year, $enrollments): void
    {
        $studentsByClass = $enrollments->groupBy('class_id')->map(fn ($group) => $group->pluck('student_id')->values());
        $subjectsByClass = Subject::query()->get()->groupBy('class_id')->map->values();
        $enteredBy = User::query()->where('email', 'teacher@school.local')->value('id');
        $today = JalaliDate::today();

        foreach (ClassRoom::query()->ordered()->get() as $class) {
            $subjects = $subjectsByClass->get($class->id, collect());
            $studentIds = $studentsByClass->get($class->id, collect());

            if ($subjects->isEmpty() || $studentIds->isEmpty()) {
                continue;
            }

            $exam = Exam::firstOrCreate(
                ['academic_year_id' => $year->id, 'class_id' => $class->id, 'name' => 'امتحان چهارماهه اول'],
                [
                    'term_type' => 'mid_term',
                    'start_date' => JalaliDate::addDays($today, 10),
                    'end_date' => JalaliDate::addDays($today, 14),
                    'is_published' => false,
                ]
            );

            foreach ($subjects->take(4) as $subject) {
                $examSubject = ExamSubject::firstOrCreate(
                    ['exam_id' => $exam->id, 'subject_id' => $subject->id],
                    [
                        'full_marks' => $subject->full_marks,
                        'pass_marks' => $subject->pass_marks,
                        'exam_date' => JalaliDate::addDays($today, 10),
                    ]
                );

                foreach ($studentIds as $studentId) {
                    $isAbsent = random_int(1, 20) === 1;

                    Mark::firstOrCreate(
                        ['exam_subject_id' => $examSubject->id, 'student_id' => $studentId],
                        [
                            'marks_obtained' => $isAbsent ? null : random_int((int) ($examSubject->pass_marks * 0.6), $examSubject->full_marks),
                            'is_absent' => $isAbsent,
                            'entered_by' => $enteredBy,
                        ]
                    );
                }
            }
        }
    }

    private function seedFeeInvoicesAndPayments(AcademicYear $year, $enrollments): void
    {
        $registrationFee = FeeStructure::query()
            ->where(['academic_year_id' => $year->id, 'class_id' => null, 'name' => 'حق الثبت نام'])
            ->first();
        $receivedBy = User::query()->where('email', 'accountant@school.local')->value('id');

        foreach ($enrollments as $enrollment) {
            $classFee = FeeStructure::query()
                ->where(['academic_year_id' => $year->id, 'class_id' => $enrollment->class_id, 'name' => 'شهریه ماهوار'])
                ->first();

            foreach (array_filter([$registrationFee, $classFee]) as $structure) {
                $invoice = FeeInvoice::firstOrCreate(
                    ['student_id' => $enrollment->student_id, 'fee_structure_id' => $structure->id],
                    [
                        'academic_year_id' => $year->id,
                        'amount' => $structure->amount,
                        'due_date' => JalaliDate::addDays(JalaliDate::today(), 15),
                    ]
                );

                if (! $invoice->wasRecentlyCreated || random_int(1, 3) === 1) {
                    // Leave roughly a third of freshly-created invoices unpaid
                    // on purpose, so the "outstanding balance" views have
                    // something to show too.
                    continue;
                }

                $isPartial = random_int(1, 3) === 1;

                FeePayment::create([
                    'fee_invoice_id' => $invoice->id,
                    'amount' => $isPartial ? round(((float) $structure->amount) * 0.5, 2) : $structure->amount,
                    'payment_date' => JalaliDate::today(),
                    'payment_method' => 'cash',
                    'receipt_number' => FeePayment::nextReceiptNumber(),
                    'received_by' => $receivedBy,
                ]);
            }
        }
    }

    private function seedSalaryPayments($teachers, $employees): void
    {
        $paidBy = User::query()->where('email', 'accountant@school.local')->value('id');
        // salary_month is a month-only Jalali "YYYY-MM" value (see
        // App\Models\SalaryPayment / App\Support\JalaliDate) - matches the
        // convention already used by SalaryPaymentFactory.
        $salaryMonth = substr(JalaliDate::today(), 0, 7);

        foreach ($teachers as $teacher) {
            SalaryPayment::firstOrCreate(
                ['payee_type' => 'teacher', 'payee_id' => $teacher->id, 'salary_month' => $salaryMonth],
                [
                    'amount' => $teacher->monthly_salary,
                    'payment_date' => JalaliDate::today(),
                    'payment_method' => 'bank_transfer',
                    'voucher_number' => SalaryPayment::nextVoucherNumber(),
                    'paid_by' => $paidBy,
                ]
            );
        }

        foreach ($employees as $employee) {
            SalaryPayment::firstOrCreate(
                ['payee_type' => 'employee', 'payee_id' => $employee->id, 'salary_month' => $salaryMonth],
                [
                    'amount' => $employee->monthly_salary,
                    'payment_date' => JalaliDate::today(),
                    'payment_method' => 'cash',
                    'voucher_number' => SalaryPayment::nextVoucherNumber(),
                    'paid_by' => $paidBy,
                ]
            );
        }
    }

    /**
     * A handful of non-salary expenses and miscellaneous (non-fee) income
     * entries, so the Expenses/Misc Incomes screens and the Finance
     * Overview breakdown have something real to show. Categories are
     * keyed on their (unique) name, transactions on
     * category+title+date - there's no other natural key since
     * voucher_number/receipt_number are generated, not user input - so
     * re-running this seeder never duplicates either.
     */
    private function seedExpensesAndMiscIncomes(): void
    {
        $paidBy = User::query()->where('email', 'accountant@school.local')->value('id');
        $today = JalaliDate::today();

        $expenseCategories = [
            'برق و آب' => 'مصارف برق و آب مکتب',
            'تعمیر و ترمیم' => 'ترمیم ساختمان و تجهیزات مکتب',
            'لوازم دفتر' => 'خریداری لوازم دفتری و صنفی',
        ];

        $expenseCategoryModels = [];
        foreach ($expenseCategories as $name => $description) {
            $expenseCategoryModels[$name] = ExpenseCategory::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }

        $expenses = [
            ['category' => 'برق و آب', 'title' => 'فاتوره برق ماه جاری', 'amount' => 3200],
            ['category' => 'تعمیر و ترمیم', 'title' => 'ترمیم صنف سوم', 'amount' => 1500],
            ['category' => 'لوازم دفتر', 'title' => 'خریداری قلم و کاپی', 'amount' => 900],
        ];

        foreach ($expenses as $data) {
            Expense::firstOrCreate(
                [
                    'expense_category_id' => $expenseCategoryModels[$data['category']]->id,
                    'title' => $data['title'],
                    'expense_date' => $today,
                ],
                [
                    'amount' => $data['amount'],
                    'payment_method' => 'cash',
                    'voucher_number' => Expense::nextVoucherNumber(),
                    'paid_by' => $paidBy,
                ]
            );
        }

        $incomeCategories = [
            'کمک مالی' => 'کمک‌های نقدی جامعه و خیرین',
            'کرایه هال' => 'عاید کرایه هال مکتب برای محافل',
        ];

        $incomeCategoryModels = [];
        foreach ($incomeCategories as $name => $description) {
            $incomeCategoryModels[$name] = IncomeCategory::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }

        $incomes = [
            ['category' => 'کمک مالی', 'title' => 'کمک یک تن خیر به مکتب', 'amount' => 5000],
            ['category' => 'کرایه هال', 'title' => 'کرایه هال برای یک محفل', 'amount' => 2000],
        ];

        foreach ($incomes as $data) {
            MiscIncome::firstOrCreate(
                [
                    'income_category_id' => $incomeCategoryModels[$data['category']]->id,
                    'title' => $data['title'],
                    'received_date' => $today,
                ],
                [
                    'amount' => $data['amount'],
                    'payment_method' => 'bank_transfer',
                    'receipt_number' => MiscIncome::nextReceiptNumber(),
                    'received_by' => $paidBy,
                ]
            );
        }
    }

    /**
     * Returns [array{teacher: User, student: User, parent: User}, User|null]
     * so TestDataSeeder can point a sample announcement at real accounts.
     * Most seeded profiles deliberately stay unlinked - that is the normal
     * state for most real records - but a small number get logins across
     * the three roles so "this profile can log in" is demonstrable.
     */
    private function linkSampleLoginAccounts($teachers, $students, $parents): array
    {
        $password = Hash::make(UserSeeder::DEMO_PASSWORD);
        $linked = ['teacher' => null, 'student' => null, 'parent' => null];

        foreach ($teachers->take(2) as $i => $teacher) {
            if ($teacher->user_id) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => "teacher.demo{$i}@aaiwo.local"],
                [
                    'name' => $teacher->fullName(),
                    'phone' => $teacher->phone,
                    'password' => $password,
                    'is_active' => true,
                    'preferred_locale' => 'fa',
                ]
            );
            $user->syncRoles(['Teacher']);
            $teacher->update(['user_id' => $user->id]);
            $linked['teacher'] = $user;
        }

        foreach ($students->take(2) as $i => $student) {
            if ($student->user_id) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => "student.demo{$i}@aaiwo.local"],
                [
                    'name' => $student->fullName(),
                    'phone' => $student->phone,
                    'password' => $password,
                    'is_active' => true,
                    'preferred_locale' => 'fa',
                ]
            );
            $user->syncRoles(['Student']);
            $student->update(['user_id' => $user->id]);
            $linked['student'] = $user;
        }

        $parentSender = null;

        foreach ($parents->take(2) as $i => $parent) {
            if ($parent->user_id) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => "parent.demo{$i}@aaiwo.local"],
                [
                    'name' => $parent->fullName(),
                    'phone' => $parent->phone,
                    'password' => $password,
                    'is_active' => true,
                    'preferred_locale' => 'fa',
                ]
            );
            $user->syncRoles(['Parent']);
            $parent->update(['user_id' => $user->id]);
            $linked['parent'] = $user;
            $parentSender ??= $user;
        }

        return [$linked, $parentSender];
    }

    /**
     * One student document per student with a linkable user account, plus a
     * couple for random students, so the "Documents" tab on registration
     * screens has downloadable content. The file bytes live on the private
     * secure_documents disk (never web-exposed).
     */
    private function seedStudentDocument($students): void
    {
        if (StudentDocument::query()->exists()) {
            return;
        }

        $targets = $students->take(8);

        foreach ($targets as $student) {
            $filename = 'sample-document-'.$student->id.'.txt';
            Storage::disk('secure_documents')->put(
                'student-documents/'.$student->id.'/'.$filename,
                'Sample document for '.$student->fullName().' - generated by TestDataSeeder.'
            );

            $student->documents()->create([
                'document_type' => 'birth_certificate',
                'title' => 'تصدیق ولادت '.$student->fullName(),
                'file_path' => 'student-documents/'.$student->id.'/'.$filename,
                'original_filename' => 'birth-certificate-'.$student->id.'.txt',
                'mime_type' => 'text/plain',
                'size_bytes' => Storage::disk('secure_documents')->size('student-documents/'.$student->id.'/'.$filename),
                'uploaded_by' => User::query()->where('email', 'admin@school.local')->value('id'),
            ]);
        }
    }

    /**
     * A few published announcements so the bell + announcements inbox have
     * content: a school-wide broadcast to Parent/Teacher and a direct
     * parent-to-teacher message (target_user_ids) from the first linked
     * parent to the first linked teacher, mirroring what Announcement
     * Notification fans out to. Idempotent via the title.
     */
    private function seedAnnouncements(array $linkedUsers, ?User $parentSender): void
    {
        $adminUser = User::query()->where('email', 'admin@school.local')->first();
        $authorId = $adminUser?->id;
        $now = now();

        $broadcast = Announcement::firstOrCreate(
            ['title' => 'خوش آمدید به سال تحصیلی جدید'],
            [
                'body' => 'به همه دانش‌آموزان، والدین و استادان تبریک می‌گوییم. سال تحصیلی جدید رسماً آغاز شد. لطفاً برای اطلاع از برنامه هفتگی و امتحانات به اعلانات مراجعه نمایید.',
                'target_roles' => ['Parent', 'Teacher', 'Student'],
                'created_by' => $authorId,
                'is_published' => true,
                'published_at' => $now,
            ]
        );

        if ($broadcast->wasRecentlyCreated) {
            Notification::send(
                User::query()->where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('name', ['Parent', 'Teacher', 'Student']))->get(),
                new AnnouncementNotification($broadcast)
            );
        }

        if ($linkedUsers['teacher'] && $parentSender) {
            $message = Announcement::firstOrCreate(
                ['title' => 'سؤال در مورد درس فرزندم'],
                [
                    'body' => 'با سلام و احترام، از شما خواهشمندم در مورد پیشرفت درسی فرزندم و تکلیف های اخیر توضیحاتی ارائه دهید. با تشکر.',
                    'target_roles' => ['Teacher'],
                    'target_user_ids' => [$linkedUsers['teacher']->id],
                    'created_by' => $parentSender->id,
                    'is_published' => true,
                    'published_at' => $now,
                ]
            );

            if ($message->wasRecentlyCreated) {
                Notification::send(
                    collect([$linkedUsers['teacher']]),
                    new AnnouncementNotification($message)
                );
            }
        }
    }
}
