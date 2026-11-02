<x-layouts.app :title="__('app.edit_exam')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('exams.update', $exam) }}" novalidate>
                @csrf
                @method('PUT')
                @include('exams._form')
            </form>
        </div>
    </div>
</x-layouts.app>
