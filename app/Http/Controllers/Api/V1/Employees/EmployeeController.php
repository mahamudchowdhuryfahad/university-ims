<?php

namespace App\Http\Controllers\Api\V1\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $employees = Employee::with('department')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('employee_id', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($employees);
    }

    public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name'          => ['required', 'string'],
        'employee_id'   => ['required', 'string', 'unique:employees'],
        'email'         => ['nullable', 'email', 'unique:employees'],
        'phone'         => ['nullable', 'string'],
        'designation'   => ['nullable', 'string'],
        'department_id' => ['nullable', 'exists:departments,id'],
        'status'        => ['nullable', 'in:active,inactive,resigned'],
    ]);

    $employee = Employee::create($validated);
    return $this->createdResponse($employee->load('department'), 'Employee created successfully');
}

    public function show(Employee $employee): JsonResponse
    {
        return $this->successResponse($employee->load('department'));
    }

    public function update(Request $request, Employee $employee): JsonResponse
{
    $validated = $request->validate([
        'name'          => ['sometimes', 'string'],
        'employee_id'   => ['sometimes', 'string', 'unique:employees,employee_id,' . $employee->id],
        'email'         => ['nullable', 'email', 'unique:employees,email,' . $employee->id],
        'phone'         => ['nullable', 'string'],
        'designation'   => ['nullable', 'string'],
        'department_id' => ['nullable', 'exists:departments,id'],
        'status'        => ['nullable', 'in:active,inactive,resigned'],
    ]);

    $employee->update($validated);
    return $this->successResponse($employee->fresh('department'), 'Employee updated successfully');
}

public function destroy(Employee $employee): JsonResponse
{
    if ($employee->fixedAssets()->exists()) {
        return $this->errorResponse('Cannot delete employee with assigned assets', 422);
    }

    $employee->delete();
    return $this->successResponse(null, 'Employee deleted successfully');
}
}
