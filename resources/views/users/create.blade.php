<x-layouts.app :title="__('app.add_user')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('users.store') }}" novalidate>
                @csrf
                @include('users._form')
            </form>
        </div>
    </div>
</x-layouts.app>
