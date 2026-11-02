<x-layouts.app :title="__('app.edit_teacher')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('teachers.update', $teacher) }}" enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')
                @include('teachers._form')
            </form>
        </div>
    </div>
</x-layouts.app>
