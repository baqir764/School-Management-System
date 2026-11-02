<x-layouts.app :title="__('app.edit_income_category')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('income-categories.update', $category) }}" novalidate>
                @csrf
                @method('PUT')
                @include('income-categories._form')
            </form>
        </div>
    </div>
</x-layouts.app>
