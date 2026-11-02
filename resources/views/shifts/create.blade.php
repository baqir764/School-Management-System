<x-layouts.app :title="__('app.add_shift')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('shifts.store') }}" novalidate>
                @csrf
                @include('shifts._form')
            </form>
        </div>
    </div>
</x-layouts.app>
