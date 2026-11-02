<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\ImportTeachersRequest;
use App\Http\Requests\Teachers\StoreTeacherRequest;
use App\Http\Requests\Teachers\UpdateTeacherRequest;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $teachers = Teacher::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = "%{$request->string('search')}%";
                $query->where(fn ($q) => $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_code', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('teachers.index', ['teachers' => $teachers]);
    }

    public function create(): View
    {
        return view('teachers.create', [
            'linkableUsers' => $this->linkableUsers(),
            'shifts' => Shift::query()->ordered()->get(),
        ]);
    }

    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'date_of_birth', 'joining_date']);
        $data['employee_code'] = Teacher::nextEmployeeCode();
        $data['date_of_birth'] = $request->jalaliDateFromInput('date_of_birth');
        $data['joining_date'] = $request->jalaliDateFromInput('joining_date');

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('teacher-photos', 'public');
        }

        $teacher = Teacher::create($data);

        AuditLogger::log('create', 'teachers', $teacher->id, null, $teacher->only(['employee_code', 'first_name', 'last_name']));

        return redirect()->route('teachers.index')->with('status', __('app.teacher_created'));
    }

    public function show(Teacher $teacher): View
    {
        return view('teachers.show', [
            'teacher' => $teacher->load(['user', 'homeroomSections.classRoom', 'subjects.classRoom']),
        ]);
    }

    public function edit(Teacher $teacher): View
    {
        return view('teachers.edit', [
            'teacher' => $teacher,
            'linkableUsers' => $this->linkableUsers($teacher),
            'shifts' => Shift::query()->ordered()->get(),
        ]);
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $original = $teacher->only(['first_name', 'last_name', 'status', 'employment_type']);

        $data = $request->safe()->except(['photo', 'date_of_birth', 'joining_date']);
        $data['date_of_birth'] = $request->jalaliDateFromInput('date_of_birth');
        $data['joining_date'] = $request->jalaliDateFromInput('joining_date');

        if ($request->hasFile('photo')) {
            if ($teacher->photo_path) {
                Storage::disk('public')->delete($teacher->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('teacher-photos', 'public');
        }

        $teacher->update($data);

        AuditLogger::log('update', 'teachers', $teacher->id, $original, $teacher->only(['first_name', 'last_name', 'status', 'employment_type']));

        return redirect()->route('teachers.index')->with('status', __('app.teacher_updated'));
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        abort_if(
            $teacher->homeroomSections()->exists() || $teacher->subjects()->exists() || $teacher->salaryPayments()->exists(),
            403,
            __('app.cannot_delete_teacher_in_use')
        );

        $teacher->delete();

        AuditLogger::log('delete', 'teachers', $teacher->id);

        return redirect()->route('teachers.index')->with('status', __('app.teacher_deleted'));
    }

    private function exportHeaders(): array
    {
        return [
            __('app.field_employee_code'), __('app.field_first_name'), __('app.field_last_name'),
            __('app.field_father_name'), __('app.field_gender'), __('app.field_date_of_birth'),
            __('app.field_national_id'), __('app.field_phone'), __('app.field_email'), __('app.field_address'),
            __('app.field_qualification'), __('app.field_specialization'), __('app.field_joining_date'),
            __('app.field_employment_type'), __('app.field_monthly_salary'), __('app.column_status'),
        ];
    }

    public function export(): StreamedResponse
    {
        $teachers = Teacher::query()->orderBy('first_name')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Teachers');
        $sheet->setRightToLeft(app()->getLocale() !== 'en');
        $sheet->fromArray($this->exportHeaders(), null, 'A1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);

        $row = 2;
        foreach ($teachers as $teacher) {
            $sheet->fromArray([
                $teacher->employee_code, $teacher->first_name, $teacher->last_name,
                $teacher->father_name,
                $teacher->gender ? __('app.gender_'.$teacher->gender) : null,
                $teacher->date_of_birth,
                $teacher->national_id, $teacher->phone, $teacher->email, $teacher->address,
                $teacher->qualification, $teacher->specialization, $teacher->joining_date,
                $teacher->employment_type, $teacher->monthly_salary,
                $teacher->status === 'active' ? __('app.status_active') : $teacher->status,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'teachers-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(ImportTeachersRequest $request): RedirectResponse
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($rows); // header row

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            [$employeeCode, $firstName, $lastName, $fatherName, $gender, $dateOfBirth, $nationalId, $phone, $email, $address, $qualification, $specialization, $joiningDate, $employmentType, $monthlySalary, $status] = array_pad($row, 16, null);

            $employeeCode = trim((string) $employeeCode);
            $firstName = trim((string) $firstName);
            $lastName = trim((string) $lastName);

            if ($employeeCode === '' && $firstName === '' && $lastName === '') {
                continue;
            }

            $parsedDateOfBirth = $this->parseJalaliDateCell($dateOfBirth);
            $parsedJoiningDate = $this->parseJalaliDateCell($joiningDate);

            if ($parsedDateOfBirth === false || $parsedJoiningDate === false) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_invalid_jalali_date')]);

                continue;
            }

            $employmentTypeValue = mb_strtolower(trim((string) $employmentType));

            $data = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'father_name' => trim((string) $fatherName) ?: null,
                'gender' => $this->normalizeGenderCell($gender),
                'date_of_birth' => $parsedDateOfBirth,
                'national_id' => trim((string) $nationalId) ?: null,
                'phone' => trim((string) $phone) ?: null,
                'email' => trim((string) $email) ?: null,
                'address' => trim((string) $address) ?: null,
                'qualification' => trim((string) $qualification) ?: null,
                'specialization' => trim((string) $specialization) ?: null,
                'joining_date' => $parsedJoiningDate,
                'employment_type' => in_array($employmentTypeValue, Teacher::EMPLOYMENT_TYPES, true) ? $employmentTypeValue : 'full_time',
                'monthly_salary' => is_numeric($monthlySalary) ? (float) $monthlySalary : null,
                'status' => in_array(mb_strtolower(trim((string) $status)), Teacher::STATUSES, true) ? mb_strtolower(trim((string) $status)) : 'active',
            ];

            $validator = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['nullable', 'email', 'max:190'],
            ]);

            if ($validator->fails()) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => $validator->errors()->first()]);

                continue;
            }

            $existing = $employeeCode !== '' ? Teacher::where('employee_code', $employeeCode)->first() : null;
            $original = $existing?->only(['first_name', 'last_name', 'status']);

            if ($existing) {
                $existing->update($data);
                $teacher = $existing;
            } else {
                $data['employee_code'] = Teacher::nextEmployeeCode();
                $teacher = Teacher::create($data);
            }

            AuditLogger::log($existing ? 'update' : 'create', 'teachers', $teacher->id, $original, $teacher->only(['first_name', 'last_name', 'status']));
            $imported++;
        }

        if ($errors) {
            return redirect()->route('teachers.index')
                ->with('status', __('app.teachers_imported_with_errors', ['count' => $imported]))
                ->with('import_errors', $errors);
        }

        return redirect()->route('teachers.index')->with('status', __('app.teachers_imported', ['count' => $imported]));
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
     * "Male"/"Female") as well as the raw stored value itself. Returns
     * null (not false) for anything unrecognized - gender is optional on
     * a teacher, so an unrecognized value is simply left unset rather than
     * failing the whole row.
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

    /**
     * Users eligible to be linked to a teacher record: not already linked
     * to any teacher/student/parent profile (except this teacher's own
     * current link, when editing).
     */
    private function linkableUsers(?Teacher $teacher = null)
    {
        return User::query()
            ->doesntHave('student')
            ->doesntHave('parentGuardian')
            ->where(function ($query) use ($teacher) {
                $query->doesntHave('teacher');
                if ($teacher?->user_id) {
                    $query->orWhere('id', $teacher->user_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
