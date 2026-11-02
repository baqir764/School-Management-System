<x-layouts.app :title="__('app.edit_parent')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('parents.update', $parent) }}" enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')
                @include('parents._form')
            </form>
        </div>
    </div>
</x-layouts.app>
