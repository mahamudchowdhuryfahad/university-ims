<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string'],
            'email'     => ['required', 'email', 'unique:users'],
            'password'  => ['required', 'min:8'],
            'role'      => ['nullable', 'string', 'in:requester,fixed-asset-admin,consumable-admin,store-admin,super-admin'],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['role'])) {
            $user->assignRole($validated['role']);
        }

        return $this->createdResponse($user->load('roles'), 'User created successfully');
    }

    public function show(User $user): JsonResponse
    {
        return $this->successResponse($user->load('roles'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'string'],
            'email'     => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'password'  => ['nullable', 'min:8'],
            'role'      => ['nullable', 'string', 'in:requester,fixed-asset-admin,consumable-admin,store-admin,super-admin'],
            'is_active' => ['boolean'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Don't allow deactivating own account
        if (isset($validated['is_active']) && !$validated['is_active'] && $user->id === auth()->id()) {
            return $this->errorResponse('Cannot deactivate your own account', 422);
        }

        $user->update(collect($validated)->except('role')->toArray());

        if (!empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return $this->successResponse($user->fresh('roles'), 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return $this->errorResponse('Cannot delete your own account', 422);
        }

        $user->delete();
        return $this->successResponse(null, 'User deleted successfully');
    }

    public function toggleStatus(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return $this->errorResponse('Cannot deactivate your own account', 422);
        }

        $user->update(['is_active' => !$user->is_active]);
        return $this->successResponse($user, 'User status updated successfully');
    }

    public function approve(Request $request, User $user): JsonResponse
{
    $validated = $request->validate([
        'role' => ['required', 'string', 'in:requester,fixed-asset-admin,consumable-admin,store-admin,super-admin'],
    ]);

    $user->syncRoles([$validated['role']]);
    $user->update(['is_active' => true]);

    // Auto-create employee record if not exists
    if (!\App\Models\Employee::where('email', $user->email)->exists()) {
        $empId = 'EMP-' . strtoupper(\Illuminate\Support\Str::random(6));
        \App\Models\Employee::create([
            'name'        => $user->name,
            'email'       => $user->email,
            'employee_id' => $empId,
            'status'      => 'active',
            'is_active'   => true,
        ]);
    }
    return $this->successResponse($user->fresh('roles'), 'User approved successfully');
}
}
