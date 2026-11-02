<x-layouts.app :title="__('app.add_subject')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('subjects.store') }}" novalidate>
                @csrf
                @include('subjects._form')
            </form>
        </div>
    </div>
</x-layouts.app>
