<x-layouts.app :title="__('app.add_teacher')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('teachers.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @include('teachers._form')
            </form>
        </div>
    </div>
</x-layouts.app>
