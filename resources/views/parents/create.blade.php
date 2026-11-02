<x-layouts.app :title="__('app.add_parent')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('parents.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @include('parents._form')
            </form>
        </div>
    </div>
</x-layouts.app>
