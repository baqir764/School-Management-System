<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreIncomeCategoryRequest;
use App\Http\Requests\Finance\UpdateIncomeCategoryRequest;
use App\Models\IncomeCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Income categories (e.g. "Donation", "Grant", "Hall Rental"): master data
 * a MiscIncome (a non-fee receipt) is filed under - mirrors
 * ExpenseCategoryController exactly, on the "money in" side.
 */
class IncomeCategoryController extends Controller
{
    public function index(): View
    {
        return view('income-categories.index', [
            'categories' => IncomeCategory::query()
                ->withCount('miscIncomes')
                ->ordered()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('income-categories.create');
    }

    public function store(StoreIncomeCategoryRequest $request): RedirectResponse
    {
        $category = IncomeCategory::create($request->validated());

        AuditLogger::log('create', 'income_categories', $category->id, null, $category->only(['name']));

        return redirect()->route('income-categories.index')->with('status', __('app.income_category_created'));
    }

    public function edit(IncomeCategory $income_category): View
    {
        return view('income-categories.edit', ['category' => $income_category]);
    }

    public function update(UpdateIncomeCategoryRequest $request, IncomeCategory $income_category): RedirectResponse
    {
        $original = $income_category->only(['name', 'description', 'is_active']);

        $income_category->update($request->validated());

        AuditLogger::log('update', 'income_categories', $income_category->id, $original, $income_category->only(['name', 'description', 'is_active']));

        return redirect()->route('income-categories.index')->with('status', __('app.income_category_updated'));
    }

    public function destroy(IncomeCategory $income_category): RedirectResponse
    {
        abort_if($income_category->miscIncomes()->exists(), 403, __('app.cannot_delete_income_category_in_use'));

        $income_category->delete();

        AuditLogger::log('delete', 'income_categories', $income_category->id);

        return redirect()->route('income-categories.index')->with('status', __('app.income_category_deleted'));
    }
}
