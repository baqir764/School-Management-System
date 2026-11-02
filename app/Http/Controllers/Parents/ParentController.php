<?php

namespace App\Http\Controllers\Parents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parents\ImportParentsRequest;
use App\Http\Requests\Parents\LinkStudentRequest;
use App\Http\Requests\Parents\StoreParentRequest;
use App\Http\Requests\Parents\UpdateParentRequest;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParentController extends Controller
{
    public function index(Request $request): View
    {
        $parents = ParentGuardian::query()
            ->withCount('students')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = "%{$request->string('search')}%";
                $query->where(fn ($q) => $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('parents.index', ['parents' => $parents]);
    }

    public function create(): View
    {
        return view('parents.create', ['linkableUsers' => $this->linkableUsers()]);
    }

    public function store(StoreParentRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('photo');

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('parent-photos', 'public');
        }

        $parent = ParentGuardian::create($data);

        AuditLogger::log('create', 'parents', $parent->id, null, $parent->only(['first_name', 'last_name', 'phone']));

        return redirect()->route('parents.show', $parent)->with('status', __('app.parent_created'));
    }

    public function show(ParentGuardian $parent): View
    {
        return view('parents.show', [
            'parent' => $parent->load('students'),
            'linkableStudents' => Student::query()->whereDoesntHave('parents', fn ($q) => $q->where('parent_id', $parent->id))->orderBy('first_name')->get(),
        ]);
    }

    public function edit(ParentGuardian $parent): View
    {
        return view('parents.edit', ['parent' => $parent, 'linkableUsers' => $this->linkableUsers($parent)]);
    }

    public function update(UpdateParentRequest $request, ParentGuardian $parent): RedirectResponse
    {
        $original = $parent->only(['first_name', 'last_name', 'phone']);

        $data = $request->safe()->except('photo');

        if ($request->hasFile('photo')) {
            if ($parent->photo_path) {
                Storage::disk('public')->delete($parent->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('parent-photos', 'public');
        }

        $parent->update($data);

        AuditLogger::log('update', 'parents', $parent->id, $original, $parent->only(['first_name', 'last_name', 'phone']));

        return redirect()->route('parents.show', $parent)->with('status', __('app.parent_updated'));
    }

    public function destroy(ParentGuardian $parent): RedirectResponse
    {
        $parent->students()->detach();
        $parent->delete();

        AuditLogger::log('delete', 'parents', $parent->id);

        return redirect()->route('parents.index')->with('status', __('app.parent_deleted'));
    }

    public function linkStudent(LinkStudentRequest $request, ParentGuardian $parent): RedirectResponse
    {
        $parent->students()->syncWithoutDetaching([
            $request->integer('student_id') => [
                'relationship' => $request->string('relationship'),
                'is_primary_contact' => $request->boolean('is_primary_contact'),
            ],
        ]);

        AuditLogger::log('link_student', 'parents', $parent->id, null, ['student_id' => $request->integer('student_id')]);

        return back()->with('status', __('app.student_linked'));
    }

    public function unlinkStudent(ParentGuardian $parent, Student $student): RedirectResponse
    {
        $parent->students()->detach($student->id);

        AuditLogger::log('unlink_student', 'parents', $parent->id, null, ['student_id' => $student->id]);

        return back()->with('status', __('app.student_unlinked'));
    }

    private function exportHeaders(): array
    {
        return [
            __('app.field_first_name'), __('app.field_last_name'), __('app.field_national_id'),
            __('app.field_occupation'), __('app.field_phone'), __('app.field_email'), __('app.field_address'),
            __('app.field_admission_number'),
        ];
    }

    public function export(): StreamedResponse
    {
        $parents = ParentGuardian::query()->with('students')->orderBy('first_name')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Parents');
        $sheet->setRightToLeft(app()->getLocale() !== 'en');
        $sheet->fromArray($this->exportHeaders(), null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $row = 2;
        foreach ($parents as $parent) {
            $sheet->fromArray([
                $parent->first_name, $parent->last_name, $parent->national_id,
                $parent->occupation, $parent->phone, $parent->email, $parent->address,
                $parent->students->pluck('admission_number')->implode(', '),
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'parents-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(ImportParentsRequest $request): RedirectResponse
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($rows); // header row

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            [$firstName, $lastName, $nationalId, $occupation, $phone, $email, $address, $admissionNumbers] = array_pad($row, 8, null);

            $firstName = trim((string) $firstName);
            $lastName = trim((string) $lastName);
            $phone = trim((string) $phone);

            if ($firstName === '' && $lastName === '' && $phone === '') {
                continue;
            }

            $data = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'national_id' => trim((string) $nationalId) ?: null,
                'occupation' => trim((string) $occupation) ?: null,
                'phone' => $phone,
                'email' => trim((string) $email) ?: null,
                'address' => trim((string) $address) ?: null,
            ];

            $validator = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'phone' => ['required', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:190'],
            ]);

            if ($validator->fails()) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => $validator->errors()->first()]);

                continue;
            }

            // No reliable natural key exists for a parent (national_id is
            // optional and unenforced) - the same phone+first+last name
            // combination is treated as "the same parent" so re-importing
            // an exported file updates rather than duplicates, but two
            // genuinely different parents that happen to share all three
            // will still merge into one update. That trade-off is
            // preferable to guaranteed duplicates on every re-import.
            $existing = ParentGuardian::query()
                ->where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->where('phone', $phone)
                ->first();

            if ($existing) {
                $existing->update($data);
                $parent = $existing;
            } else {
                $parent = ParentGuardian::create($data);
            }

            AuditLogger::log($existing ? 'update' : 'create', 'parents', $parent->id, null, $parent->only(['first_name', 'last_name', 'phone']));
            $imported++;

            // Optional comma-separated admission numbers: link this parent
            // to existing students as a Guardian, without touching any
            // relationship/primary-contact flag a user already set by hand
            // (syncWithoutDetaching only adds missing links, same as the
            // "Link Student" action on the parent's own page).
            $admissionNumbers = trim((string) $admissionNumbers);
            if ($admissionNumbers !== '') {
                $studentIds = Student::query()
                    ->whereIn('admission_number', array_map('trim', explode(',', $admissionNumbers)))
                    ->pluck('id');

                foreach ($studentIds as $studentId) {
                    $parent->students()->syncWithoutDetaching([
                        $studentId => ['relationship' => 'Guardian', 'is_primary_contact' => false],
                    ]);
                }
            }
        }

        if ($errors) {
            return redirect()->route('parents.index')
                ->with('status', __('app.parents_imported_with_errors', ['count' => $imported]))
                ->with('import_errors', $errors);
        }

        return redirect()->route('parents.index')->with('status', __('app.parents_imported', ['count' => $imported]));
    }

    private function linkableUsers(?ParentGuardian $parent = null)
    {
        return User::query()
            ->doesntHave('student')
            ->doesntHave('teacher')
            ->where(function ($query) use ($parent) {
                $query->doesntHave('parentGuardian');
                if ($parent?->user_id) {
                    $query->orWhere('id', $parent->user_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
