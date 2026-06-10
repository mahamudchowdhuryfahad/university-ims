<?php

namespace App\Http\Controllers\Api\V1\Schools;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $schools = School::withCount('departments')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($schools);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string'],
            'code'      => ['required', 'string', 'unique:schools'],
            'dean_name' => ['nullable', 'string'],
            'phone'     => ['nullable', 'string'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $school = School::create($validated);
        return $this->createdResponse($school, 'School created successfully');
    }

    public function show(School $school): JsonResponse
    {
        return $this->successResponse($school->load('departments'));
    }

    public function update(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'string'],
            'code'      => ['sometimes', 'string', 'unique:schools,code,' . $school->id],
            'dean_name' => ['nullable', 'string'],
            'phone'     => ['nullable', 'string'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $school->update($validated);
        return $this->successResponse($school, 'School updated successfully');
    }

    public function destroy(School $school): JsonResponse
    {
        if ($school->departments()->exists()) {
            return $this->errorResponse('Cannot delete school with existing departments', 422);
        }

        $school->delete();
        return $this->successResponse(null, 'School deleted successfully');
    }
}
