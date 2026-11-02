<x-layouts.app :title="__('app.add_student')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @include('students._form')
            </form>
        </div>
    </div>
</x-layouts.app>
