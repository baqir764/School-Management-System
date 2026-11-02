<x-layouts.app :title="__('app.edit_section')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('sections.update', $section) }}" novalidate>
                @csrf
                @method('PUT')
                @include('sections._form')
            </form>
        </div>
    </div>
</x-layouts.app>
