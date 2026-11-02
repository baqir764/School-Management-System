<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreMiscIncomeRequest;
use App\Models\IncomeCategory;
use App\Models\MiscIncome;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Miscellaneous (non-fee) income (دریافت‌های متفرقه) - donations, grants,
 * hall rental, etc. Mirrors ExpenseController on the "money in" side, and
 * FeePaymentController's receipt pattern; like Expense, a recorded entry is
 * never edited or deleted here.
 */
class MiscIncomeController extends Controller
{
    public function index(Request $request): View
    {
        [$defaultYear, $defaultMonth] = JalaliDate::parts(JalaliDate::today());
        $month = $request->integer('month') ?: $defaultMonth;
        $year = $request->integer('year') ?: $defaultYear;
        $categoryId = $request->integer('income_category_id') ?: null;

        $from = JalaliDate::startOfMonth($year, $month);
        $to = JalaliDate::endOfMonth($year, $month);

        $incomes = MiscIncome::query()
            ->with(['category', 'receivedBy'])
            ->whereBetween('received_date', [$from, $to])
            ->when($categoryId, fn ($q) => $q->where('income_category_id', $categoryId))
            ->orderByDesc('received_date')
            ->paginate(20)
            ->withQueryString();

        return view('misc-incomes.index', [
            'incomes' => $incomes,
            'month' => $month,
            'year' => $year,
            'categoryId' => $categoryId,
            'categories' => IncomeCategory::query()->active()->ordered()->get(),
            'years' => $this->availableYears(),
        ]);
    }

    public function create(): View
    {
        return view('misc-incomes.create', [
            'categories' => IncomeCategory::query()->active()->ordered()->get(),
        ]);
    }

    public function store(StoreMiscIncomeRequest $request): RedirectResponse
    {
        $income = MiscIncome::create([
            'income_category_id' => $request->validated('income_category_id'),
            'title' => $request->validated('title'),
            'amount' => $request->validated('amount'),
            'received_date' => $request->jalaliDateFromInput('received_date'),
            'payment_method' => $request->validated('payment_method'),
            'receipt_number' => MiscIncome::nextReceiptNumber(),
            'received_by' => $request->user()->id,
            'remarks' => $request->validated('remarks'),
        ]);

        AuditLogger::log('create', 'misc_incomes', $income->id, null, $income->only([
            'income_category_id', 'title', 'amount', 'received_date',
        ]));

        return redirect()->route('misc-incomes.receipt', $income)->with('status', __('app.misc_income_recorded'));
    }

    public function receipt(MiscIncome $misc_income): View
    {
        $misc_income->load(['category', 'receivedBy']);

        return view('misc-incomes.receipt', ['income' => $misc_income]);
    }

    private function availableYears(): array
    {
        $currentYear = JalaliDate::parts(JalaliDate::today())[0];

        return range($currentYear, $currentYear - 4);
    }
}
