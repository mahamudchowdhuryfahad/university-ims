<?php

namespace App\Http\Controllers\Api\V1\Buildings;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $buildings = Building::withCount('rooms')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($buildings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string'],
            'code'      => ['required', 'string', 'unique:buildings'],
            'address'   => ['nullable', 'string'],
            'floors'    => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $building = Building::create($validated);
        return $this->createdResponse($building, 'Building created successfully');
    }

    public function show(Building $building): JsonResponse
    {
        return $this->successResponse($building->load('rooms'));
    }

    public function update(Request $request, Building $building): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'string'],
            'code'      => ['sometimes', 'string', 'unique:buildings,code,' . $building->id],
            'address'   => ['nullable', 'string'],
            'floors'    => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $building->update($validated);
        return $this->successResponse($building, 'Building updated successfully');
    }

    public function destroy(Building $building): JsonResponse
    {
        $building->delete();
        return $this->successResponse(null, 'Building deleted successfully');
    }
}
