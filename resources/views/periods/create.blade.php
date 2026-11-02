<x-layouts.app :title="__('app.add_period')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('periods.store') }}" novalidate>
                @csrf
                @include('periods._form')
            </form>
        </div>
    </div>
</x-layouts.app>
