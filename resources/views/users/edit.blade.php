<x-layouts.app :title="__('app.edit_user')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('users.update', $user) }}" novalidate>
                @csrf
                @method('PUT')
                @include('users._form')
            </form>
        </div>
    </div>
</x-layouts.app>
