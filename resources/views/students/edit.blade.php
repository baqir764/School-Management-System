<x-layouts.app :title="__('app.edit_student')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')
                @include('students._form')
            </form>
        </div>
    </div>
</x-layouts.app>
