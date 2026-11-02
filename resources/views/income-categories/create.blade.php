<x-layouts.app :title="__('app.add_income_category')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('income-categories.store') }}" novalidate>
                @csrf
                @include('income-categories._form')
            </form>
        </div>
    </div>
</x-layouts.app>
