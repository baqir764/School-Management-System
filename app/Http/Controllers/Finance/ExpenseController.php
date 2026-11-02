<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Non-salary expenses (مصارف متفرقه) - rent, utilities, maintenance,
 * supplies, etc. Mirrors SalaryPaymentController: a transaction, once
 * recorded, is never edited or deleted here (corrections mean a fresh,
 * separate entry) - only create/store/voucher plus a filtered history.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        [$defaultYear, $defaultMonth] = JalaliDate::parts(JalaliDate::today());
        $month = $request->integer('month') ?: $defaultMonth;
        $year = $request->integer('year') ?: $defaultYear;
        $categoryId = $request->integer('expense_category_id') ?: null;

        $from = JalaliDate::startOfMonth($year, $month);
        $to = JalaliDate::endOfMonth($year, $month);

        $expenses = Expense::query()
            ->with(['category', 'paidBy'])
            ->whereBetween('expense_date', [$from, $to])
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'month' => $month,
            'year' => $year,
            'categoryId' => $categoryId,
            'categories' => ExpenseCategory::query()->active()->ordered()->get(),
            'years' => $this->availableYears(),
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', [
            'categories' => ExpenseCategory::query()->active()->ordered()->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = Expense::create([
            'expense_category_id' => $request->validated('expense_category_id'),
            'title' => $request->validated('title'),
            'amount' => $request->validated('amount'),
            'expense_date' => $request->jalaliDateFromInput('expense_date'),
            'payment_method' => $request->validated('payment_method'),
            'voucher_number' => Expense::nextVoucherNumber(),
            'paid_by' => $request->user()->id,
            'remarks' => $request->validated('remarks'),
        ]);

        AuditLogger::log('create', 'expenses', $expense->id, null, $expense->only([
            'expense_category_id', 'title', 'amount', 'expense_date',
        ]));

        return redirect()->route('expenses.voucher', $expense)->with('status', __('app.expense_recorded'));
    }

    public function voucher(Expense $expense): View
    {
        $expense->load(['category', 'paidBy']);

        return view('expenses.voucher', ['expense' => $expense]);
    }

    private function availableYears(): array
    {
        $currentYear = JalaliDate::parts(JalaliDate::today())[0];

        return range($currentYear, $currentYear - 4);
    }
}
