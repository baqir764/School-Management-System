<x-layouts.app :title="__('app.add_fee_structure')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('fee-structures.store') }}" novalidate>
                @csrf
                @include('fee-structures._form')
            </form>
        </div>
    </div>
</x-layouts.app>
