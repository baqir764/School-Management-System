<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\ImportEmployeesRequest;
use App\Http\Requests\Payroll\StoreEmployeeRequest;
use App\Http\Requests\Payroll\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = "%{$request->string('search')}%";
                $query->where(fn ($q) => $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_code', 'like', $term)
                    ->orWhere('position', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', ['employees' => $employees]);
    }

    public function create(): View
    {
        return view('employees.create', ['shifts' => Shift::query()->ordered()->get()]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['joining_date']);
        $data['employee_code'] = Employee::nextEmployeeCode();
        $data['joining_date'] = $request->jalaliDateFromInput('joining_date');

        $employee = Employee::create($data);

        AuditLogger::log('create', 'employees', $employee->id, null, $employee->only(['employee_code', 'first_name', 'last_name', 'position']));

        return redirect()->route('employees.index')->with('status', __('app.employee_created'));
    }

    public function show(Employee $employee): View
    {
        return view('employees.show', ['employee' => $employee]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.edit', ['employee' => $employee, 'shifts' => Shift::query()->ordered()->get()]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $original = $employee->only(['first_name', 'last_name', 'position', 'status', 'monthly_salary']);

        $data = $request->safe()->except(['joining_date']);
        $data['joining_date'] = $request->jalaliDateFromInput('joining_date');

        $employee->update($data);

        AuditLogger::log('update', 'employees', $employee->id, $original, $employee->only(['first_name', 'last_name', 'position', 'status', 'monthly_salary']));

        return redirect()->route('employees.index')->with('status', __('app.employee_updated'));
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        abort_if($employee->salaryPayments()->exists(), 403, __('app.cannot_delete_employee_in_use'));

        $employee->delete();

        AuditLogger::log('delete', 'employees', $employee->id);

        return redirect()->route('employees.index')->with('status', __('app.employee_deleted'));
    }

    private function exportHeaders(): array
    {
        return [
            __('app.field_employee_code'), __('app.field_first_name'), __('app.field_last_name'),
            __('app.field_position'), __('app.field_phone'), __('app.field_national_id'),
            __('app.field_monthly_salary'), __('app.field_joining_date'), __('app.column_status'),
        ];
    }

    public function export(): StreamedResponse
    {
        $employees = Employee::query()->orderBy('first_name')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Employees');
        $sheet->setRightToLeft(app()->getLocale() !== 'en');
        $sheet->fromArray($this->exportHeaders(), null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $row = 2;
        foreach ($employees as $employee) {
            $sheet->fromArray([
                $employee->employee_code, $employee->first_name, $employee->last_name,
                $employee->position, $employee->phone, $employee->national_id,
                $employee->monthly_salary, $employee->joining_date,
                $employee->status === 'active' ? __('app.status_active') : $employee->status,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'employees-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(ImportEmployeesRequest $request): RedirectResponse
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($rows); // header row

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            [$employeeCode, $firstName, $lastName, $position, $phone, $nationalId, $monthlySalary, $joiningDate, $status] = array_pad($row, 9, null);

            $employeeCode = trim((string) $employeeCode);
            $firstName = trim((string) $firstName);
            $lastName = trim((string) $lastName);

            if ($employeeCode === '' && $firstName === '' && $lastName === '') {
                continue;
            }

            $parsedJoiningDate = $this->parseJalaliDateCell($joiningDate);
            if ($parsedJoiningDate === false) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_invalid_jalali_date')]);

                continue;
            }

            $statusValue = mb_strtolower(trim((string) $status));
            $isDisabledToken = collect(['inactive', 'terminated', '0', 'false'])->contains($statusValue)
                || mb_strtolower(trim((string) __('app.status_disabled'))) === $statusValue;

            $data = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'position' => trim((string) $position),
                'phone' => trim((string) $phone) ?: null,
                'national_id' => trim((string) $nationalId) ?: null,
                'monthly_salary' => is_numeric($monthlySalary) ? (float) $monthlySalary : null,
                'joining_date' => $parsedJoiningDate,
                'status' => in_array($statusValue, Employee::STATUSES, true) ? $statusValue : ($isDisabledToken ? 'inactive' : 'active'),
            ];

            $validator = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'position' => ['required', 'string', 'max:100'],
                'monthly_salary' => ['required', 'numeric', 'min:0'],
            ]);

            if ($validator->fails()) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => $validator->errors()->first()]);

                continue;
            }

            $existing = $employeeCode !== '' ? Employee::where('employee_code', $employeeCode)->first() : null;
            $original = $existing?->only(['first_name', 'last_name', 'position', 'status']);

            if ($existing) {
                $existing->update($data);
                $employee = $existing;
            } else {
                $data['employee_code'] = Employee::nextEmployeeCode();
                $employee = Employee::create($data);
            }

            AuditLogger::log($existing ? 'update' : 'create', 'employees', $employee->id, $original, $employee->only(['first_name', 'last_name', 'position', 'status']));
            $imported++;
        }

        if ($errors) {
            return redirect()->route('employees.index')
                ->with('status', __('app.employees_imported_with_errors', ['count' => $imported]))
                ->with('import_errors', $errors);
        }

        return redirect()->route('employees.index')->with('status', __('app.employees_imported', ['count' => $imported]));
    }

    /**
     * A cell's raw value to a Jalali "YYYY-MM-DD" string: null for an empty
     * cell, false for a non-empty value that isn't a real Jalali date -
     * the caller turns false into a row-level import error rather than
     * silently storing a null/garbage date.
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
}
