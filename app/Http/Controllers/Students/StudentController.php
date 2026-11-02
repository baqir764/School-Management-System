<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ImportStudentsRequest;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        // ?trashed=1 switches the list to the trash bin (soft-deleted
        // students only), where each row can be restored or permanently
        // erased - see restore()/forceDestroy() below.
        $showingTrashed = $request->boolean('trashed');

        $students = Student::query()
            ->when($showingTrashed,
                fn ($query) => $query->onlyTrashed(),
                fn ($query) => $query->with(['enrollments' => fn ($q) => $q->active()->with(['classRoom', 'section'])]))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = "%{$request->string('search')}%";
                $query->where(fn ($q) => $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('admission_number', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('class_id'), function ($query) use ($request) {
                $query->whereHas('enrollments', fn ($q) => $q->active()->where('class_id', $request->integer('class_id')));
            })
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'classes' => ClassRoom::query()->ordered()->get(),
            'showingTrashed' => $showingTrashed,
            'trashedCount' => Student::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        return view('students.create');
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['photo', 'date_of_birth', 'admission_date']);
            $data['admission_number'] = Student::nextAdmissionNumber();
            $data['date_of_birth'] = $request->jalaliDateFromInput('date_of_birth');
            $data['admission_date'] = $request->jalaliDateFromInput('admission_date');

            if ($request->hasFile('photo')) {
                $data['photo_path'] = $request->file('photo')->store('student-photos', 'public');
            }

            return Student::create($data);
        });

        AuditLogger::log('create', 'students', $student->id, null, $student->only(['admission_number', 'first_name', 'last_name']));

        return redirect()->route('students.show', $student)->with('status', __('app.student_created'));
    }

    public function show(Student $student): View
    {
        return view('students.show', [
            'student' => $student->load([
                'documents', 'parents',
                'enrollments' => fn ($q) => $q->with(['academicYear', 'classRoom', 'section'])->orderByDesc('enrollment_date'),
            ]),
        ]);
    }

    public function edit(Student $student): View
    {
        return view('students.edit', ['student' => $student]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $original = $student->only(['first_name', 'last_name', 'status']);

        $data = $request->safe()->except(['photo', 'date_of_birth', 'admission_date']);
        $data['date_of_birth'] = $request->jalaliDateFromInput('date_of_birth');
        $data['admission_date'] = $request->jalaliDateFromInput('admission_date');

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('public')->delete($student->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('student-photos', 'public');
        }

        $student->update($data);

        AuditLogger::log('update', 'students', $student->id, $original, $student->only(['first_name', 'last_name', 'status']));

        return redirect()->route('students.show', $student)->with('status', __('app.student_updated'));
    }

    /**
     * Soft delete (archive): the student moves to the trash bin (?trashed=1
     * on the index) and can be restored. Students with enrollment history
     * must have those enrollments permanently removed first, or be erased
     * via forceDestroy() instead.
     */
    public function destroy(Student $student): RedirectResponse
    {
        abort_if($student->enrollments()->exists(), 403, __('app.cannot_delete_student_with_enrollments'));

        $student->delete();

        AuditLogger::log('delete', 'students', $student->id);

        return redirect()->route('students.index')->with('status', __('app.student_deleted'));
    }

    /**
     * Restores a soft-deleted student from the trash bin back to the active
     * roster. Takes a raw id (not implicit binding) because implicitly
     * bound models exclude trashed rows, which is exactly what this acts
     * on.
     */
    public function restore(string $student): RedirectResponse
    {
        $record = Student::onlyTrashed()->findOrFail($student);

        $record->restore();

        AuditLogger::log('restore', 'students', $record->id);

        return redirect()->route('students.index')->with('status', __('app.student_restored'));
    }

    /**
     * PERMANENT deletion: erases the student row from the database for
     * good, together with every related record. The foreign-key cascades
     * (enrollments, documents, attendance records, marks, fee invoices +
     * their payments, parent links) remove the DB rows; uploaded files
     * (photo + secure documents) and the orphaned student-portal login are
     * cleaned up explicitly since no FK covers the filesystem or the
     * users table in this direction.
     */
    public function forceDestroy(string $student): RedirectResponse
    {
        $record = Student::withTrashed()->findOrFail($student);
        $recordId = $record->id;
        $admissionNumber = $record->admission_number;

        DB::transaction(function () use ($record) {
            if ($record->photo_path) {
                Storage::disk('public')->delete($record->photo_path);
            }

            $documentPaths = $record->documents()->withTrashed()->pluck('file_path');
            foreach ($documentPaths as $path) {
                Storage::disk('secure_documents')->delete($path);
            }

            $record->parents()->detach();

            $linkedUser = $record->user;

            $record->forceDelete();

            // A student-portal login left behind would be an orphaned
            // account with no profile - soft-delete it (keeps the audit
            // trail intact), but only when it really is a student account,
            // never a staff account that merely happens to be linked.
            if ($linkedUser && $linkedUser->hasRole('Student')) {
                $linkedUser->delete();
            }
        });

        AuditLogger::log('force_delete', 'students', $recordId, ['admission_number' => $admissionNumber]);

        return redirect()->route('students.index')->with('status', __('app.student_permanently_deleted'));
    }

    private function exportHeaders(): array
    {
        return [
            __('app.field_admission_number'), __('app.field_first_name'), __('app.field_last_name'),
            __('app.field_father_name'), __('app.field_grandfather_name'), __('app.field_gender'),
            __('app.field_date_of_birth'), __('app.field_place_of_birth'), __('app.field_national_id'),
            __('app.field_nationality'), __('app.field_religion'), __('app.field_blood_group'),
            __('app.field_phone'), __('app.field_email'), __('app.field_address'),
            __('app.field_province'), __('app.field_district'), __('app.field_admission_date'),
            __('app.column_status'), __('app.field_class_name'), __('app.field_section_name'),
        ];
    }

    public function export(): StreamedResponse
    {
        $students = Student::query()
            ->with(['enrollments' => fn ($q) => $q->active()->with(['classRoom', 'section'])])
            ->orderBy('first_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Students');
        $sheet->setRightToLeft(app()->getLocale() !== 'en');
        $sheet->fromArray($this->exportHeaders(), null, 'A1');
        $sheet->getStyle('A1:U1')->getFont()->setBold(true);

        $row = 2;
        foreach ($students as $student) {
            $currentEnrollment = $student->enrollments->first();

            $sheet->fromArray([
                $student->admission_number, $student->first_name, $student->last_name,
                $student->father_name, $student->grandfather_name,
                $student->gender ? __('app.gender_'.$student->gender) : null,
                $student->date_of_birth, $student->place_of_birth, $student->national_id,
                $student->nationality, $student->religion, $student->blood_group,
                $student->phone, $student->email, $student->address,
                $student->province, $student->district, $student->admission_date,
                $student->status === 'active' ? __('app.status_active') : $student->status,
                $currentEnrollment?->classRoom?->name, $currentEnrollment?->section?->name,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'U') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'students-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(ImportStudentsRequest $request): RedirectResponse
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($rows); // header row

        $currentYear = AcademicYear::current();
        $classesByName = ClassRoom::query()->get()->keyBy(fn ($class) => mb_strtolower(trim($class->name)));

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            [
                $admissionNumber, $firstName, $lastName, $fatherName, $grandfatherName, $gender,
                $dateOfBirth, $placeOfBirth, $nationalId, $nationality, $religion, $bloodGroup,
                $phone, $email, $address, $province, $district, $admissionDate, $status,
                $className, $sectionName,
            ] = array_pad($row, 21, null);

            $admissionNumber = trim((string) $admissionNumber);
            $firstName = trim((string) $firstName);
            $lastName = trim((string) $lastName);

            if ($admissionNumber === '' && $firstName === '' && $lastName === '') {
                continue;
            }

            $parsedDateOfBirth = $this->parseJalaliDateCell($dateOfBirth);
            $parsedAdmissionDate = $this->parseJalaliDateCell($admissionDate);
            // An empty admission_date cell defaults to today; an invalid,
            // non-empty one is still a row error (checked right below) -
            // parseJalaliDateCell's null (empty) vs false (invalid)
            // distinction has to be read before defaulting it, since '?:'
            // would treat both the same and silently swallow a bad value.
            if ($parsedAdmissionDate === null) {
                $parsedAdmissionDate = JalaliDate::today();
            }

            if ($parsedDateOfBirth === false || $parsedAdmissionDate === false) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_invalid_jalali_date')]);

                continue;
            }

            $statusValue = mb_strtolower(trim((string) $status));

            $data = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'father_name' => trim((string) $fatherName) ?: null,
                'grandfather_name' => trim((string) $grandfatherName) ?: null,
                'gender' => $this->normalizeGenderCell($gender),
                'date_of_birth' => $parsedDateOfBirth,
                'place_of_birth' => trim((string) $placeOfBirth) ?: null,
                'national_id' => trim((string) $nationalId) ?: null,
                'nationality' => trim((string) $nationality) ?: 'Afghan',
                'religion' => trim((string) $religion) ?: null,
                'blood_group' => trim((string) $bloodGroup) ?: null,
                'phone' => trim((string) $phone) ?: null,
                'email' => trim((string) $email) ?: null,
                'address' => trim((string) $address) ?: null,
                'province' => trim((string) $province) ?: null,
                'district' => trim((string) $district) ?: null,
                'admission_date' => $parsedAdmissionDate,
                'status' => in_array($statusValue, Student::STATUSES, true) ? $statusValue : 'active',
            ];

            $validator = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'gender' => ['required', 'in:male,female'],
            ]);

            if ($validator->fails()) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => $validator->errors()->first()]);

                continue;
            }

            $existing = $admissionNumber !== '' ? Student::where('admission_number', $admissionNumber)->first() : null;
            $original = $existing?->only(['first_name', 'last_name', 'status']);

            if ($existing) {
                $existing->update($data);
                $student = $existing;
            } else {
                $data['admission_number'] = Student::nextAdmissionNumber();
                $student = Student::create($data);
            }

            AuditLogger::log($existing ? 'update' : 'create', 'students', $student->id, $original, $student->only(['first_name', 'last_name', 'status']));
            $imported++;

            // Optional class/section columns: best-effort enrollment into
            // the current academic year, skipped quietly (not a row error)
            // when there's no active year or the names don't resolve, since
            // enrollment is a bonus of this import, not its main purpose -
            // a name typo here shouldn't block the student record itself
            // from being imported.
            $className = trim((string) $className);
            if ($currentYear && $className !== '') {
                $class = $classesByName->get(mb_strtolower($className));
                $section = $class
                    ? Section::query()->where('class_id', $class->id)->where('name', trim((string) $sectionName))->first()
                    : null;

                if ($class && $section && ! Enrollment::where(['student_id' => $student->id, 'academic_year_id' => $currentYear->id])->exists()) {
                    Enrollment::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $currentYear->id,
                        'class_id' => $class->id,
                        'section_id' => $section->id,
                        'enrollment_date' => JalaliDate::today(),
                        'status' => 'active',
                    ]);
                }
            }
        }

        if ($errors) {
            return redirect()->route('students.index')
                ->with('status', __('app.students_imported_with_errors', ['count' => $imported]))
                ->with('import_errors', $errors);
        }

        return redirect()->route('students.index')->with('status', __('app.students_imported', ['count' => $imported]));
    }

    /**
     * A cell's raw value to a Jalali "YYYY-MM-DD" string: null for an empty
     * cell, false for a non-empty value that isn't a real Jalali date.
     */
    private function parseJalaliDateCell($value): string|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{3,4})-(\d{1,2})-(\d{1,2})$/', str_replace('/', '-', $value), $m)) {
            return false;
        }

        [, $year, $month, $day] = $m;

        if (! JalaliDate::isValidDate((int) $year, (int) $month, (int) $day)) {
            return false;
        }

        return JalaliDate::fromParts((int) $year, (int) $month, (int) $day);
    }

    /**
     * A gender cell to the stored 'male'/'female' value, accepting
     * whatever label export() would have written for the app's three
     * locales (Dari "مذکر"/"مونث", Pashto "نارینه"/"ښځینه", English
     * "Male"/"Female") as well as the raw stored value itself - so a file
     * exported in one locale and re-imported after switching locale (or
     * filled in by hand using either language) still round-trips. Returns
     * null, not false, for anything unrecognized: an unrecognized gender
     * is caught by the 'required' validation rule right after this is
     * called, same as a genuinely empty cell.
     */
    private function normalizeGenderCell($value): ?string
    {
        $value = trim((string) $value);

        return match (true) {
            in_array(mb_strtolower($value), ['male', 'مذکر', 'نارینه'], true) => 'male',
            in_array(mb_strtolower($value), ['female', 'مونث', 'مؤنث', 'ښځینه'], true) => 'female',
            default => null,
        };
    }
}
