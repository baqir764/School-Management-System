<x-layouts.app :title="__('app.add_class')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('classes.store') }}" novalidate>
                @csrf
                @include('classes._form')
            </form>
        </div>
    </div>
</x-layouts.app>
