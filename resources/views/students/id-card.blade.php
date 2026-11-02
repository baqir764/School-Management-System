@php
    $school = \App\Models\Setting::current();
    $student = $students->first();
@endphp
<x-layouts.app :title="__('app.id_card_print_title')">
    @include('students._id-card-print-styles')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
        <h1 class="h4 mb-0">{{ __('app.id_card_print_title') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>{{ __('app.students_id_card') }}
            </button>
        </div>
    </div>

    <p class="text-muted small no-print">{{ __('app.id_card_print_instructions') }}</p>

    <div class="id-cards-page" id="id-cards-front">
        @foreach ($students as $s)
            @include('students._id-card-front', ['student' => $s, 'school' => $school])
        @endforeach
    </div>
    <div class="id-cards-page" id="id-cards-back">
        @foreach ($students as $s)
            @include('students._id-card-back', ['student' => $s, 'school' => $school])
        @endforeach
    </div>

    @include('students._id-card-scripts')
</x-layouts.app>
