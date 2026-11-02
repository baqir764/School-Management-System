<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseCategoryRequest;
use App\Http\Requests\Finance\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Expense categories (e.g. "Utilities", "Maintenance", "Supplies"): master
 * data an Expense is filed under, exactly like Shift is master data a
 * Class/Teacher/Employee is assigned. Lives under 'finance.manage' /
 * 'finance.view', the same split as fees.manage/.view and payroll.manage/.view.
 */
class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('expense-categories.index', [
            'categories' => ExpenseCategory::query()
                ->withCount('expenses')
                ->ordered()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('expense-categories.create');
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $category = ExpenseCategory::create($request->validated());

        AuditLogger::log('create', 'expense_categories', $category->id, null, $category->only(['name']));

        return redirect()->route('expense-categories.index')->with('status', __('app.expense_category_created'));
    }

    public function edit(ExpenseCategory $expense_category): View
    {
        return view('expense-categories.edit', ['category' => $expense_category]);
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expense_category): RedirectResponse
    {
        $original = $expense_category->only(['name', 'description', 'is_active']);

        $expense_category->update($request->validated());

        AuditLogger::log('update', 'expense_categories', $expense_category->id, $original, $expense_category->only(['name', 'description', 'is_active']));

        return redirect()->route('expense-categories.index')->with('status', __('app.expense_category_updated'));
    }

    public function destroy(ExpenseCategory $expense_category): RedirectResponse
    {
        abort_if($expense_category->expenses()->exists(), 403, __('app.cannot_delete_expense_category_in_use'));

        $expense_category->delete();

        AuditLogger::log('delete', 'expense_categories', $expense_category->id);

        return redirect()->route('expense-categories.index')->with('status', __('app.expense_category_deleted'));
    }
}
