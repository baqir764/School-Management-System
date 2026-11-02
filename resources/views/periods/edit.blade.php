<x-layouts.app :title="__('app.edit_period')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('periods.update', $period) }}" novalidate>
                @csrf
                @method('PUT')
                @include('periods._form')
            </form>
        </div>
    </div>
</x-layouts.app>
