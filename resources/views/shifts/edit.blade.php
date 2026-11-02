<x-layouts.app :title="__('app.edit_shift')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('shifts.update', $shift) }}" novalidate>
                @csrf
                @method('PUT')
                @include('shifts._form')
            </form>
        </div>
    </div>
</x-layouts.app>
