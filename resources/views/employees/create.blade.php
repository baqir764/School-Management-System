<x-layouts.app :title="__('app.add_employee')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('employees.store') }}" novalidate>
                @csrf
                @include('employees._form')
            </form>
        </div>
    </div>
</x-layouts.app>
