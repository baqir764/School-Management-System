<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreSalaryPaymentRequest;
use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalaryPaymentController extends Controller
{
    /**
     * Payroll register: every salary payment across both Teachers and
     * Employees for a selected Jalali month/year, optionally narrowed to
     * one payee type. salary_month is a month-only Jalali "YYYY-MM"
     * string (see App\Models\SalaryPayment), so filtering it is a plain
     * exact-match where() rather than whereYear()/whereMonth() (which
     * assume a Gregorian DATE column). Unlike Fee Invoices, status isn't
     * derived here - a salary payment is a single completed transaction,
     * not a balance-tracking invoice - so this is a plain
     * filtered/paginated query, no PHP-side status filtering needed.
     */
    public function index(Request $request): View
    {
        [$defaultYear, $defaultMonth] = JalaliDate::parts(JalaliDate::today());
        $month = $request->integer('month') ?: $defaultMonth;
        $year = $request->integer('year') ?: $defaultYear;
        $payeeType = $request->string('payee_type')->toString() ?: null;

        $payments = SalaryPayment::query()
            ->with(['payee', 'paidBy'])
            ->where('salary_month', sprintf('%04d-%02d', $year, $month))
            ->when($payeeType, fn ($q) => $q->where('payee_type', $payeeType))
            ->orderByDesc('payment_date')
            ->paginate(20)
            ->withQueryString();

        return view('salary-payments.index', [
            'payments' => $payments,
            'month' => $month,
            'year' => $year,
            'payeeType' => $payeeType,
            'years' => $this->availableYears(),
        ]);
    }

    public function create(Request $request): View
    {
        $payeeType = $request->string('payee_type')->toString();
        $payee = $this->resolvePayee($payeeType, $request->integer('payee_id'));

        return view('salary-payments.create', [
            'payee' => $payee,
            'payeeType' => $payeeType,
        ]);
    }

    public function store(StoreSalaryPaymentRequest $request): RedirectResponse
    {
        $payeeType = $request->validated('payee_type');
        $payee = $this->resolvePayee($payeeType, (int) $request->validated('payee_id'));

        $payment = SalaryPayment::create([
            'payee_type' => $payeeType,
            'payee_id' => $payee->id,
            'amount' => $request->validated('amount'),
            'discount_amount' => $request->validated('discount_amount') ?? 0,
            'salary_month' => $request->salaryMonthFromInput(),
            'payment_date' => $request->jalaliDateFromInput('payment_date'),
            'payment_method' => $request->validated('payment_method'),
            'voucher_number' => SalaryPayment::nextVoucherNumber(),
            'paid_by' => $request->user()->id,
            'remarks' => $request->validated('remarks'),
        ]);

        AuditLogger::log('create', 'salary_payments', $payment->id, null, $payment->only([
            'payee_type', 'payee_id', 'amount', 'discount_amount', 'salary_month',
        ]));

        return redirect()->route('salary-payments.receipt', $payment)->with('status', __('app.salary_payment_recorded'));
    }

    public function receipt(SalaryPayment $salary_payment): View
    {
        $salary_payment->load(['payee', 'paidBy']);

        return view('salary-payments.receipt', ['payment' => $salary_payment]);
    }

    private function resolvePayee(?string $type, ?int $id)
    {
        $model = match ($type) {
            'teacher' => Teacher::class,
            'employee' => Employee::class,
            default => null,
        };

        abort_if(! $model || ! $id, 404);

        return $model::findOrFail($id);
    }

    private function availableYears(): array
    {
        $currentYear = JalaliDate::parts(JalaliDate::today())[0];

        return range($currentYear, $currentYear - 4);
    }
}
