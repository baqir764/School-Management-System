<x-layouts.app :title="__('app.edit_expense_category')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('expense-categories.update', $category) }}" novalidate>
                @csrf
                @method('PUT')
                @include('expense-categories._form')
            </form>
        </div>
    </div>
</x-layouts.app>
