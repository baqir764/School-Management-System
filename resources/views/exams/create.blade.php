<x-layouts.app :title="__('app.add_exam')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('exams.store') }}" novalidate>
                @csrf
                @include('exams._form')
            </form>
        </div>
    </div>
</x-layouts.app>
