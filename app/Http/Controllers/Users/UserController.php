<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * All routes for this controller are wrapped in the 'permission:users.manage'
 * middleware at the route-group level (routes/web.php) rather than here -
 * Laravel 11 moved controller-constructor middleware to a static
 * HasMiddleware contract, and keeping the rule at the route group keeps it
 * visible in one place alongside every other module's permission gate.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = "%{$request->string('search')}%";
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role'))))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['roles' => Role::orderBy('name')->get()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->string('password')),
            'is_active' => $request->boolean('is_active', true),
            'must_change_password' => true,
        ]);

        $user->syncRoles($request->input('roles'));

        AuditLogger::log('create', 'users', $user->id, null, $user->only(['name', 'email', 'is_active']));

        return redirect()->route('users.index')->with('status', __('app.user_created'));
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $original = $user->only(['name', 'email', 'is_active']);

        $user->fill([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'phone' => $request->input('phone'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->string('password'));
            $user->must_change_password = true;
        }

        $user->save();
        $user->syncRoles($request->input('roles'));

        AuditLogger::log('update', 'users', $user->id, $original, $user->only(['name', 'email', 'is_active']));

        return redirect()->route('users.index')->with('status', __('app.user_updated'));
    }

    /**
     * Toggle active/disabled. Per master spec section 41 (soft delete) and
     * section 30 ("Disable users"), user accounts are disabled, never
     * hard-deleted, so historical records they authored stay intact.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, __('app.cannot_disable_self'));

        $user->update(['is_active' => ! $user->is_active]);

        AuditLogger::log($user->is_active ? 'enable' : 'disable', 'users', $user->id);

        return back()->with('status', __('app.user_status_updated'));
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $temporaryPassword = Str::password(12);

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        AuditLogger::log('reset_password', 'users', $user->id);

        return back()->with('status', __('app.password_reset_to', ['password' => $temporaryPassword]));
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, __('app.cannot_disable_self'));

        $user->delete();

        AuditLogger::log('delete', 'users', $user->id);

        return redirect()->route('users.index')->with('status', __('app.user_deleted'));
    }
}
