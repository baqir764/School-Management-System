<x-layouts.app :title="__('app.add_academic_year')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('academic-years.store') }}" novalidate>
                @csrf
                @include('academic-years._form')
            </form>
        </div>
    </div>
</x-layouts.app>
