<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\ImportSubjectsRequest;
use App\Http\Requests\Academics\StoreSubjectRequest;
use App\Http\Requests\Academics\UpdateSubjectRequest;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubjectController extends Controller
{
    public function index(): View
    {
        // Grouped by class (accordion in the view) instead of one flat,
        // paginated table - with a school's subject count this small, a
        // per-class group the user can expand/collapse is easier to scan
        // than paging through subjects sorted only by name.
        return view('subjects.index', [
            'classes' => ClassRoom::query()
                ->with(['subjects' => fn ($query) => $query->with('teacher')->orderBy('name')])
                ->ordered()
                ->get(),
            'unassignedSubjects' => Subject::query()
                ->whereNull('class_id')
                ->with('teacher')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('subjects.create', $this->formData());
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        AuditLogger::log('create', 'subjects', $subject->id, null, $subject->only(['name', 'code']));

        return redirect()->route('subjects.index')->with('status', __('app.subject_created'));
    }

    public function edit(Subject $subject): View
    {
        return view('subjects.edit', array_merge($this->formData(), ['subject' => $subject]));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $original = $subject->only(['name', 'code', 'class_id', 'teacher_id']);

        $subject->update($request->validated());

        AuditLogger::log('update', 'subjects', $subject->id, $original, $subject->only(['name', 'code', 'class_id', 'teacher_id']));

        return redirect()->route('subjects.index')->with('status', __('app.subject_updated'));
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        AuditLogger::log('delete', 'subjects', $subject->id);

        return redirect()->route('subjects.index')->with('status', __('app.subject_deleted'));
    }

    /**
     * Column order shared between export() (what it writes) and import()
     * (what it expects to read back) - keeping both in one place means
     * they can't drift apart. class/teacher are matched by their display
     * name rather than a raw id, since a name is what a human editing the
     * spreadsheet can actually see and get right.
     */
    private function exportHeaders(): array
    {
        return [
            __('app.field_subject_code'),
            __('app.field_subject_name'),
            __('app.field_class_name'),
            __('app.field_subject_teacher'),
            __('app.field_full_marks'),
            __('app.field_pass_marks'),
            __('app.column_status'),
        ];
    }

    public function export(): StreamedResponse
    {
        $subjects = Subject::query()->with(['classRoom', 'teacher'])->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Subjects');
        $sheet->setRightToLeft(app()->getLocale() !== 'en');

        $sheet->fromArray($this->exportHeaders(), null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $row = 2;
        foreach ($subjects as $subject) {
            $sheet->fromArray([
                $subject->code,
                $subject->name,
                $subject->classRoom?->name,
                $subject->teacher?->fullName(),
                $subject->full_marks,
                $subject->pass_marks,
                $subject->is_active ? __('app.status_active') : __('app.status_disabled'),
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'subjects-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Upserts by code (same as SchoolSubjectsSeeder): re-importing a
     * previously exported, edited sheet updates the matching subjects
     * instead of duplicating them. Bad rows are skipped and reported
     * rather than aborting the whole file, so one typo doesn't cost every
     * other correct row in a 50+ row sheet.
     */
    public function import(ImportSubjectsRequest $request): RedirectResponse
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        // Row 1 is the header written by export() - skip it.
        array_shift($rows);

        $classesByName = ClassRoom::query()->get()->keyBy(fn ($class) => mb_strtolower(trim($class->name)));
        $teachersByName = Teacher::query()->get()->keyBy(fn ($teacher) => mb_strtolower(trim($teacher->fullName())));

        // "Disabled" as written by export() in any of the app's locales -
        // not just whichever locale the importing admin currently has
        // active - so a sheet exported in Dari still imports correctly
        // even if read back under the English UI.
        $disabledTokens = collect(['en', 'fa', 'ps'])
            ->map(fn ($locale) => mb_strtolower(trim(__('app.status_disabled', [], $locale))))
            ->push('0')->push('inactive')->push('false')
            ->all();

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // 0-based $index, plus the header row already shifted off

            [$code, $name, $className, $teacherName, $fullMarks, $passMarks, $status] = array_pad($row, 7, null);

            $code = trim((string) $code);
            $name = trim((string) $name);

            if ($code === '' && $name === '') {
                continue; // blank row
            }

            $data = [
                'name' => $name,
                'code' => $code,
                'full_marks' => is_numeric($fullMarks) ? (int) $fullMarks : null,
                'pass_marks' => is_numeric($passMarks) ? (int) $passMarks : null,
                'is_active' => ! in_array(mb_strtolower(trim((string) $status)), $disabledTokens, true),
            ];

            $className = trim((string) $className);
            if ($className !== '') {
                $class = $classesByName->get(mb_strtolower($className));

                if (! $class) {
                    $errors[] = __('app.import_row_error_unknown_class', ['line' => $lineNumber, 'class' => $className]);

                    continue;
                }

                $data['class_id'] = $class->id;
            } else {
                $data['class_id'] = null;
            }

            // A teacher name that doesn't match anyone is left unassigned
            // rather than failing the row - the mark scale and class
            // placement matter more than who's teaching it, and that can
            // always be fixed later from the subject's edit form.
            $teacherName = trim((string) $teacherName);
            $data['teacher_id'] = $teacherName !== ''
                ? $teachersByName->get(mb_strtolower($teacherName))?->id
                : null;

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:150'],
                'code' => ['required', 'string', 'max:30'],
                'full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
                'pass_marks' => ['required', 'integer', 'min:0', 'lte:full_marks'],
            ]);

            if ($validator->fails()) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => $validator->errors()->first()]);

                continue;
            }

            $existing = Subject::where('code', $data['code'])->first();
            $original = $existing?->only(['name', 'code', 'class_id', 'teacher_id']);

            $subject = Subject::updateOrCreate(['code' => $data['code']], $data);

            AuditLogger::log($existing ? 'update' : 'create', 'subjects', $subject->id, $original, $subject->only(['name', 'code', 'class_id', 'teacher_id']));

            $imported++;
        }

        if ($errors) {
            return redirect()->route('subjects.index')
                ->with('status', __('app.subjects_imported_with_errors', ['count' => $imported]))
                ->with('import_errors', $errors);
        }

        return redirect()->route('subjects.index')->with('status', __('app.subjects_imported', ['count' => $imported]));
    }

    private function formData(): array
    {
        return [
            'classes' => ClassRoom::query()->ordered()->get(),
            'teachers' => Teacher::query()->active()->orderBy('first_name')->get(),
        ];
    }
}
