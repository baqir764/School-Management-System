<x-layouts.app :title="__('app.edit_academic_year')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('academic-years.update', $academicYear) }}" novalidate>
                @csrf
                @method('PUT')
                @include('academic-years._form')
            </form>
        </div>
    </div>
</x-layouts.app>
