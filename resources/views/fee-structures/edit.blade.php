<x-layouts.app :title="__('app.edit_fee_structure')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('fee-structures.update', $feeStructure) }}" novalidate>
                @csrf
                @method('PUT')
                @include('fee-structures._form')
            </form>
        </div>
    </div>
</x-layouts.app>
