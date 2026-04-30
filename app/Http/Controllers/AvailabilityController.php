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
      'service' => ['nullable', 'integer', 'min:1'],
      'date' => ['nullable', 'date_format:Y-m-d'],
    ]);

    $slots = AvailabilitySlot::query()
      ->publicAvailable()
      ->when($validated['date'] ?? null, fn ($query, $date) => $query->whereDate('start_at', $date))
      ->orderBy('start_at')
      ->get()
      ->map(fn (AvailabilitySlot $slot) => PortalFormat::slot($slot))
      ->values();

    return response()->json($slots);
  }
}
