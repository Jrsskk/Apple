<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%");
            }))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status']),
            'roles' => array_map(fn ($r) => ['value' => $r->value, 'label' => ucfirst($r->value)], UserRole::cases()),
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => ucfirst($s->value)], UserStatus::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => array_map(fn ($r) => ['value' => $r->value, 'label' => ucfirst($r->value)], UserRole::cases()),
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => ucfirst($s->value)], UserStatus::cases()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());
        $this->auditLog->log(auth()->user(), 'create', 'users', "Created user {$user->full_name}.");

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => array_map(fn ($r) => ['value' => $r->value, 'label' => ucfirst($r->value)], UserRole::cases()),
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => ucfirst($s->value)], UserStatus::cases()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        $this->auditLog->log(auth()->user(), 'update', 'users', "Updated user {$user->full_name}.");

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot archive your own account.');
        $user->update(['status' => UserStatus::Archived]);
        $user->delete();
        $this->auditLog->log(auth()->user(), 'archive', 'users', "Archived user {$user->full_name}.");

        return back()->with('success', 'User archived.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $user->update(['password' => Hash::make('password')]);
        $this->auditLog->log(auth()->user(), 'reset_password', 'users', "Reset password for {$user->full_name}.");

        return back()->with('success', 'Password reset to default (development only).');
    }
}
