<x-layouts.app :title="__('app.add_grade_scale')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('grade-scales.store') }}" novalidate>
                @csrf
                @include('grade-scales._form')
            </form>
        </div>
    </div>
</x-layouts.app>
