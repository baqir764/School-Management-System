<x-layouts.app :title="__('app.edit_class')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('classes.update', $class) }}" novalidate>
                @csrf
                @method('PUT')
                @include('classes._form')
            </form>
        </div>
    </div>
</x-layouts.app>
