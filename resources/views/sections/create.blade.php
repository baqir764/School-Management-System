<x-layouts.app :title="__('app.add_section')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('sections.store') }}" novalidate>
                @csrf
                @include('sections._form')
            </form>
        </div>
    </div>
</x-layouts.app>
