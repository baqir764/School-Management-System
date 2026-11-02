<x-layouts.app :title="__('app.edit_employee')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('employees.update', $employee) }}" novalidate>
                @csrf
                @method('PUT')
                @include('employees._form')
            </form>
        </div>
    </div>
</x-layouts.app>
