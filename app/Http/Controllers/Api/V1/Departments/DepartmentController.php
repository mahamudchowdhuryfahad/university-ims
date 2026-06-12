<?php

namespace App\Http\Controllers\Api\V1\Departments;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $departments = Department::with('school')
            ->withCount('employees')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->when($request->school_id, fn($q, $id) => $q->where('school_id', $id))
            ->when($request->has_school, fn($q) => $q->whereNotNull('school_id'))
            ->when($request->no_school, fn($q) => $q->whereNull('school_id'))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string'],
            'code'      => ['required', 'string', 'unique:departments'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'head_name' => ['nullable', 'string'],
            'phone'     => ['nullable', 'string'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $department = Department::create($validated);
        return $this->createdResponse($department->load('school'), 'Department created successfully');
    }

    public function show(Department $department): JsonResponse
    {
        return $this->successResponse($department->load(['school', 'employees']));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'string'],
            'code'      => ['sometimes', 'string', 'unique:departments,code,' . $department->id],
            'school_id' => ['nullable', 'exists:schools,id'],
            'head_name' => ['nullable', 'string'],
            'phone'     => ['nullable', 'string'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $department->update($validated);
        return $this->successResponse($department->fresh('school'), 'Department updated successfully');
    }

    public function destroy(Department $department): JsonResponse
    {
        if ($department->employees()->exists()) {
            return $this->errorResponse('Cannot delete department with existing employees', 422);
        }

        if ($department->fixedAssets()->exists()) {
            return $this->errorResponse('Cannot delete department with assigned assets', 422);
        }

        $department->delete();
        return $this->successResponse(null, 'Department deleted successfully');
    }
}
