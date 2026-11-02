<x-layouts.app :title="__('app.edit_grade_scale')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('grade-scales.update', $gradeScale) }}" novalidate>
                @csrf
                @method('PUT')
                @include('grade-scales._form')
            </form>
        </div>
    </div>
</x-layouts.app>
