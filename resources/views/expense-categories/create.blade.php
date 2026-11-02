<x-layouts.app :title="__('app.add_expense_category')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('expense-categories.store') }}" novalidate>
                @csrf
                @include('expense-categories._form')
            </form>
        </div>
    </div>
</x-layouts.app>
