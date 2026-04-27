<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Support\PortalFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
  public function index(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'doctor' => ['nullable', 'integer', 'min:1'],
      'clinic' => ['nullable', 'integer', 'min:1'],
      'service' => ['nullable', 'integer', 'min:1'],
      'specialty' => ['nullable', 'integer', 'min:1'],
      'date' => ['nullable', 'date_format:Y-m-d'],
    ]);

    $slots = AvailabilitySlot::query()
      ->with(['doctor.specialty', 'clinic'])
      ->publicAvailable()
      ->when($validated['doctor'] ?? null, fn ($query, $doctor) => $query->where('doctor_id', $doctor))
      ->when($validated['clinic'] ?? null, fn ($query, $clinic) => $query->where('clinic_id', $clinic))
      ->when($validated['service'] ?? null, function ($query, $service): void {
        $query->whereHas('doctor.services', fn ($services) => $services
          ->where('medical_services.id', $service)
          ->where('is_active', true));
      })
      ->when($validated['specialty'] ?? null, fn ($query, $specialty) => $query->whereHas('doctor', fn ($doctor) => $doctor->where('specialty_id', $specialty)))
      ->when($validated['date'] ?? null, fn ($query, $date) => $query->whereDate('start_at', $date))
      ->orderBy('start_at')
      ->get()
      ->map(fn (AvailabilitySlot $slot) => PortalFormat::slot($slot))
      ->values();

    return response()->json($slots);
  }
}
