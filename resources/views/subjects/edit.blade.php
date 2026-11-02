<x-layouts.app :title="__('app.edit_subject')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('subjects.update', $subject) }}" novalidate>
                @csrf
                @method('PUT')
                @include('subjects._form')
            </form>
        </div>
    </div>
</x-layouts.app>
