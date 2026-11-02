<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\StoreFeeInvoiceGenerationRequest;
use App\Http\Requests\Fees\UpdateFeeInvoiceDiscountRequest;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeeStructure;
use App\Notifications\FeeReminderNotification;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeeInvoiceController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Paginates filteredInvoices()'s result by hand (status can't be
     * filtered in SQL - see that method's docblock) - the school's
     * realistic data scale makes this pragmatic, the same PHP-side
     * aggregation approach Attendance/Marks already use elsewhere.
     */
    public function index(Request $request): View
    {
        [$academicYearId, $classId, $status, $search] = $this->filtersFromRequest($request);

        $invoices = $this->filteredInvoices($academicYearId, $classId, $status, $search);

        return view('fee-invoices.index', [
            'invoices' => $this->paginate($invoices, $request),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => ClassRoom::query()->active()->ordered()->get(),
            'selectedAcademicYearId' => $academicYearId,
            'selectedClassId' => $classId,
            'selectedStatus' => $status,
            'search' => $search,
        ]);
    }

    /**
     * A single printable fee notice for one student's parent/guardian -
     * see printMany() for the shared bulk/class-wise version of this same
     * "print.blade.php" layout.
     */
    public function print(FeeInvoice $fee_invoice): View
    {
        $fee_invoice->load($this->printEagerLoads());

        return view('fee-invoices.print', ['invoices' => collect([$fee_invoice])]);
    }

    /**
     * Bulk "class-wise" printing (spec extension: fee invoice notices for
     * parents) - the exact same academic-year/class/status/search filters
     * as index(), but rendered as printable slips, several per page, so a
     * whole class's monthly fee notices print on the fewest sheets rather
     * than one page per student. Reuses filteredInvoices() so the two
     * views can never drift apart on what counts as "matching the current
     * filters."
     */
    public function printMany(Request $request): View
    {
        [$academicYearId, $classId, $status, $search] = $this->filtersFromRequest($request);

        $invoices = $this->filteredInvoices($academicYearId, $classId, $status, $search)
            ->loadMissing($this->printEagerLoads());

        return view('fee-invoices.print', ['invoices' => $invoices]);
    }

    /**
     * Sends App\Notifications\FeeReminderNotification (the same short,
     * essential-facts-only content as the printable slip) to every
     * filtered invoice's student's parent/guardian user account(s) - the
     * exact same academic-year/class/status/search filters as index()/
     * printMany(), so "filter to one class's unpaid invoices, then send
     * reminders" mirrors "...then print" exactly. Silently skips a
     * parent/guardian with no linked user account, or a deactivated one -
     * not every family necessarily has a login yet, and a reminder to an
     * account nobody can sign into would just sit unread forever.
     */
    public function sendReminders(Request $request): RedirectResponse
    {
        [$academicYearId, $classId, $status, $search] = $this->filtersFromRequest($request);

        $invoices = $this->filteredInvoices($academicYearId, $classId, $status, $search)
            ->loadMissing(['student.parents.user', 'feeStructure']);

        $notified = 0;

        foreach ($invoices as $invoice) {
            foreach ($invoice->student->parents as $parent) {
                if (! $parent->user || ! $parent->user->is_active) {
                    continue;
                }

                $parent->user->notify(new FeeReminderNotification($invoice));
                $notified++;
            }
        }

        AuditLogger::log('send_reminders', 'fee_invoices', null, null, [
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
            'invoices_matched' => $invoices->count(),
            'notifications_sent' => $notified,
        ]);

        return redirect()->route('fee-invoices.index', $request->query())
            ->with('status', __('app.fee_reminders_sent', ['count' => $notified]));
    }

    /**
     * The extra relations print()/printMany() need beyond what
     * filteredInvoices() already eager loads - each student's current
     * (active) enrollment with its classRoom/section. Kept as its own
     * array (rather than duplicated inline) since load() and
     * loadMissing() both need the exact same shape. "Active enrollment"
     * is treated as "current class" the same way
     * StudentController::index()/export() already do - not scoped to one
     * specific academic year - so a student model's
     * ->enrollments->first() reads directly as their current class here,
     * with no lazy-loading query (this app runs in strict Eloquent mode -
     * see FeeInvoice's totalPaid() docblock). The print notice itself
     * deliberately keeps to a compact one-row-per-invoice layout (student,
     * fee head/month, balance, due date) - no guardian name, no
     * amount/discount/paid breakdown - so nothing beyond that is loaded.
     */
    private function printEagerLoads(): array
    {
        return [
            'student.enrollments' => fn ($q) => $q->active()->with(['classRoom', 'section']),
            'feeStructure',
            'payments',
        ];
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?string, 3: string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            $request->integer('academic_year_id') ?: AcademicYear::current()?->id,
            $request->integer('class_id') ?: null,
            $request->string('status')->toString() ?: null,
            trim((string) $request->input('search')),
        ];
    }

    /**
     * Invoice status ('unpaid'/'partial'/'paid') is never stored - it's
     * derived live from each invoice's payments (FeeInvoice::status()) -
     * so filtering by it can't happen in SQL; this loads the (year/class/
     * search)-filtered rows and filters by status in PHP, same as
     * index()/dues() always have.
     */
    private function filteredInvoices(?int $academicYearId, ?int $classId, ?string $status, string $search): Collection
    {
        $invoices = FeeInvoice::query()
            ->with(['student', 'feeStructure', 'payments'])
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($classId, fn ($q) => $q->whereHas(
                'student.enrollments',
                fn ($q2) => $q2->where('academic_year_id', $academicYearId)->where('class_id', $classId)
            ))
            ->when($search !== '', fn ($q) => $q->whereHas('student', function ($q2) use ($search) {
                $q2->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%");
            }))
            ->orderByDesc('id')
            ->get();

        if ($status) {
            $invoices = $invoices->filter(fn (FeeInvoice $invoice) => $invoice->status() === $status)->values();
        }

        return $invoices;
    }

    public function show(FeeInvoice $fee_invoice): View
    {
        $fee_invoice->load(['student', 'feeStructure', 'academicYear', 'payments.receivedBy']);

        return view('fee-invoices.show', ['feeInvoice' => $fee_invoice]);
    }

    /**
     * The discount-only edit screen for one invoice (e.g. a sibling or
     * scholarship discount) - amount itself stays a frozen snapshot of the
     * fee structure at generation time, so this never touches it.
     */
    public function edit(FeeInvoice $fee_invoice): View
    {
        $fee_invoice->load(['student', 'feeStructure', 'payments']);

        return view('fee-invoices.edit', ['feeInvoice' => $fee_invoice]);
    }

    public function update(UpdateFeeInvoiceDiscountRequest $request, FeeInvoice $fee_invoice): RedirectResponse
    {
        $original = $fee_invoice->only(['discount_amount']);

        $fee_invoice->update(['discount_amount' => $request->validated('discount_amount') ?? 0]);

        AuditLogger::log('update', 'fee_invoices', $fee_invoice->id, $original, $fee_invoice->only(['discount_amount']));

        return redirect()->route('fee-invoices.show', $fee_invoice)->with('status', __('app.fee_invoice_discount_updated'));
    }

    public function create(FeeStructure $fee_structure): View
    {
        $fee_structure->load(['academicYear', 'classRoom']);

        $eligibleStudentIds = $this->eligibleStudentIds($fee_structure);
        $alreadyInvoiced = FeeInvoice::query()
            ->where('fee_structure_id', $fee_structure->id)
            ->whereIn('student_id', $eligibleStudentIds)
            ->count();

        return view('fee-invoices.generate', [
            'feeStructure' => $fee_structure,
            'eligibleCount' => $eligibleStudentIds->count(),
            'alreadyInvoiced' => $alreadyInvoiced,
        ]);
    }

    public function store(StoreFeeInvoiceGenerationRequest $request, FeeStructure $fee_structure): RedirectResponse
    {
        $eligibleStudentIds = $this->eligibleStudentIds($fee_structure);

        $existingStudentIds = FeeInvoice::query()
            ->where('fee_structure_id', $fee_structure->id)
            ->pluck('student_id');

        $toCreate = $eligibleStudentIds->diff($existingStudentIds);
        $created = 0;

        $dueDate = $request->jalaliDateFromInput('due_date');

        DB::transaction(function () use ($toCreate, $fee_structure, $dueDate, &$created) {
            foreach ($toCreate as $studentId) {
                FeeInvoice::create([
                    'student_id' => $studentId,
                    'fee_structure_id' => $fee_structure->id,
                    'academic_year_id' => $fee_structure->academic_year_id,
                    'amount' => $fee_structure->amount,
                    'due_date' => $dueDate,
                ]);
                $created++;
            }
        });

        AuditLogger::log('generate', 'fee_invoices', $fee_structure->id, null, [
            'fee_structure_id' => $fee_structure->id,
            'created' => $created,
            'skipped_existing' => $existingStudentIds->intersect($eligibleStudentIds)->count(),
        ]);

        return redirect()->route('fee-invoices.index', ['academic_year_id' => $fee_structure->academic_year_id])
            ->with('status', __('app.fee_invoices_generated', ['count' => $created]));
    }

    /**
     * Per-student outstanding balance across every invoice in scope - not
     * paginated, the same "report page" treatment as Exams::results(),
     * since it's already scoped to one year (and optionally one class).
     */
    public function dues(Request $request): View
    {
        $academicYearId = $request->integer('academic_year_id') ?: AcademicYear::current()?->id;
        $classId = $request->integer('class_id') ?: null;

        $invoices = FeeInvoice::query()
            ->with(['student', 'payments'])
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($classId, fn ($q) => $q->whereHas(
                'student.enrollments',
                fn ($q2) => $q2->where('academic_year_id', $academicYearId)->where('class_id', $classId)
            ))
            ->get();

        $rows = $invoices->groupBy('student_id')
            ->map(function (Collection $studentInvoices) {
                $invoiced = (float) $studentInvoices->sum('amount');
                $discount = (float) $studentInvoices->sum('discount_amount');
                $paid = (float) $studentInvoices->sum(fn (FeeInvoice $invoice) => $invoice->totalPaid());

                return [
                    'student' => $studentInvoices->first()->student,
                    'invoiced' => $invoiced,
                    'discount' => $discount,
                    'paid' => $paid,
                    'due' => round($invoiced - $discount - $paid, 2),
                ];
            })
            ->filter(fn (array $row) => $row['due'] > 0.0)
            ->sortByDesc('due')
            ->values();

        return view('fee-invoices.dues', [
            'rows' => $rows,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => ClassRoom::query()->active()->ordered()->get(),
            'selectedAcademicYearId' => $academicYearId,
            'selectedClassId' => $classId,
        ]);
    }

    /**
     * Students with an active enrollment for the fee structure's academic
     * year - narrowed to one class when the fee structure isn't
     * school-wide (see FeeStructure::appliesToAllClasses()).
     */
    private function eligibleStudentIds(FeeStructure $feeStructure): Collection
    {
        return Enrollment::query()
            ->where('academic_year_id', $feeStructure->academic_year_id)
            ->active()
            ->when($feeStructure->class_id, fn ($q) => $q->where('class_id', $feeStructure->class_id))
            ->pluck('student_id')
            ->unique()
            ->values();
    }

    private function paginate(Collection $items, Request $request): LengthAwarePaginatorContract
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
