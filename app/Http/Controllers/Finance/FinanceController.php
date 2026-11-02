<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\MiscIncome;
use App\Models\SalaryPayment;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    /**
     * Income vs expense for one Jalali calendar month: fee payments and
     * miscellaneous (non-fee) income in, against salary payments and
     * non-salary expenses out, with a net balance and a peek at the
     * underlying transactions. Every date column read here (payment_date,
     * expense_date, received_date, due_date) stores a Jalali "YYYY-MM-DD"
     * string (see App\Support\JalaliDate), so the selected month's bounds
     * are computed with JalaliDate and compared with a plain
     * whereBetween() rather than whereYear()/whereMonth() (which wrap the
     * column in MySQL's YEAR()/MONTH() and assume a Gregorian DATE column).
     *
     * Discount handling (payroll/fee discount capability): a salary
     * discount is real cash the school never pays out, so totalExpense
     * sums the *net* amount actually disbursed (amount - discount_amount)
     * - a bigger salary discount lowers expense and so counts positively
     * toward netBalance. A fee discount instead reduces revenue the
     * school never collects, but fee_payments (part of totalIncome's
     * source) is already only ever the cash actually received - a
     * discounted invoice simply can never be paid beyond its
     * already-reduced payableAmount(), so totalIncome needs no separate
     * adjustment. totalFeeDiscount is shown alongside as its own,
     * explicitly negative figure (foregone revenue) rather than folded
     * into totalIncome a second time, which would double-count it. It's
     * scoped by due_date (also a Jalali string, unlike created_at/
     * updated_at which are plain Carbon timestamps) as the closest
     * available proxy for "this period's" fee activity. Expense and
     * MiscIncome carry no discount concept, so they add straight into
     * totalExpense/totalIncome with no separate adjustment line.
     */
    public function index(Request $request): View
    {
        [$defaultYear, $defaultMonth] = JalaliDate::parts(JalaliDate::today());
        $month = $request->integer('month') ?: $defaultMonth;
        $year = $request->integer('year') ?: $defaultYear;

        $from = JalaliDate::startOfMonth($year, $month);
        $to = JalaliDate::endOfMonth($year, $month);

        $totalFeeIncome = (float) FeePayment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $totalMiscIncome = (float) MiscIncome::query()
            ->whereBetween('received_date', [$from, $to])
            ->sum('amount');

        $totalIncome = round($totalFeeIncome + $totalMiscIncome, 2);

        $totalSalaryGross = (float) SalaryPayment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $totalSalaryDiscount = (float) SalaryPayment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->sum('discount_amount');

        $totalOtherExpense = (float) Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        $totalSalaryExpense = round($totalSalaryGross - $totalSalaryDiscount, 2);
        $totalExpense = round($totalSalaryExpense + $totalOtherExpense, 2);

        $totalFeeDiscount = (float) FeeInvoice::query()
            ->whereBetween('due_date', [$from, $to])
            ->sum('discount_amount');

        $recentFeePayments = FeePayment::query()
            ->with(['invoice.student'])
            ->whereBetween('payment_date', [$from, $to])
            ->orderByDesc('payment_date')
            ->limit(10)
            ->get();

        $recentSalaryPayments = SalaryPayment::query()
            ->with(['payee'])
            ->whereBetween('payment_date', [$from, $to])
            ->orderByDesc('payment_date')
            ->limit(10)
            ->get();

        $recentMiscIncomes = MiscIncome::query()
            ->with(['category'])
            ->whereBetween('received_date', [$from, $to])
            ->orderByDesc('received_date')
            ->limit(10)
            ->get();

        $recentExpenses = Expense::query()
            ->with(['category'])
            ->whereBetween('expense_date', [$from, $to])
            ->orderByDesc('expense_date')
            ->limit(10)
            ->get();

        return view('finance.overview', [
            'month' => $month,
            'year' => $year,
            'totalIncome' => $totalIncome,
            'totalFeeIncome' => round($totalFeeIncome, 2),
            'totalMiscIncome' => round($totalMiscIncome, 2),
            'totalExpense' => $totalExpense,
            'totalSalaryExpense' => $totalSalaryExpense,
            'totalOtherExpense' => round($totalOtherExpense, 2),
            'totalSalaryDiscount' => $totalSalaryDiscount,
            'totalFeeDiscount' => $totalFeeDiscount,
            'netBalance' => round($totalIncome - $totalExpense, 2),
            'recentFeePayments' => $recentFeePayments,
            'recentSalaryPayments' => $recentSalaryPayments,
            'recentMiscIncomes' => $recentMiscIncomes,
            'recentExpenses' => $recentExpenses,
            'years' => range($defaultYear, $defaultYear - 4),
        ]);
    }
}
