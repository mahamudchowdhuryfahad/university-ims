<?php

namespace App\Http\Controllers\Api\V1\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $rooms = Room::with(['building', 'department'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('room_number', 'like', "%{$s}%"))
            ->when($request->building_id, fn($q, $id) => $q->where('building_id', $id))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($rooms);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string'],
            'room_number'   => ['required', 'string'],
            'building_id'   => ['required', 'exists:buildings,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'floor'         => ['nullable', 'integer'],
            'type'          => ['nullable', 'string'],
            'is_active'     => ['boolean'],
        ]);

        $room = Room::create($validated);
        return $this->createdResponse($room->load(['building', 'department']), 'Room created successfully');
    }

    public function show(Room $room): JsonResponse
    {
        return $this->successResponse($room->load(['building', 'department']));
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['sometimes', 'string'],
            'room_number'   => ['sometimes', 'string'],
            'building_id'   => ['sometimes', 'exists:buildings,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'floor'         => ['nullable', 'integer'],
            'type'          => ['nullable', 'string'],
            'is_active'     => ['boolean'],
        ]);

        $room->update($validated);
        return $this->successResponse($room->fresh(['building', 'department']), 'Room updated successfully');
    }

    public function destroy(Room $room): JsonResponse
    {
        $room->delete();
        return $this->successResponse(null, 'Room deleted successfully');
    }
}
