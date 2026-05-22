<?php

namespace App\Http\Controllers;

use App\Models\MedicalService;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function index(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'service' => ['nullable', 'integer', 'exists:medical_services,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $service = isset($validated['service'])
          ? MedicalService::where('is_active', true)->find($validated['service'])
          : MedicalService::where('is_active', true)->orderBy('name')->first();

        if (! $service) {
            return response()->json([]);
        }

        $doctor = $availability->primaryDoctor();
        $slots = isset($validated['date'])
          ? $availability->availableSlotsForDate($doctor, $service, $validated['date'])
          : $availability->availableDates($doctor, $service)
              ->flatMap(fn ($date) => $availability->availableSlotsForDate($doctor, $service, $date));

        $slots = $slots
            ->map(fn ($slot): array => [
                'slot_key' => $slot->key,
                'slot_start' => $slot->key,
                'start_at' => $slot->start_at?->toISOString(),
                'end_at' => $slot->end_at?->toISOString(),
            ])
            ->values();

        return response()->json($slots);
    }
}
