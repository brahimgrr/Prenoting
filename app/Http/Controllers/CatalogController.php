<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use App\Support\PortalFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
  public function services(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'search' => ['nullable', 'string'],
      'specialty' => ['nullable', 'integer', 'min:1'],
      'category' => ['nullable', 'string'],
    ]);

    $services = MedicalService::query()
      ->with('specialty')
      ->where('is_active', true)
      ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
      ->when($validated['specialty'] ?? null, fn ($query, $specialty) => $query->where('specialty_id', $specialty))
      ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
      ->orderBy('name')
      ->get()
      ->map(fn (MedicalService $service) => PortalFormat::service($service))
      ->values();

    return response()->json($services);
  }

  public function doctors(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'search' => ['nullable', 'string'],
      'specialty' => ['nullable', 'integer', 'min:1'],
    ]);

    $doctors = DoctorProfile::query()
      ->with(['specialty', 'services' => fn ($query) => $query->where('is_active', true)])
      ->where('is_active', true)
      ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('display_name', 'like', "%{$search}%"))
      ->when($validated['specialty'] ?? null, fn ($query, $specialty) => $query->where('specialty_id', $specialty))
      ->orderBy('display_name')
      ->get()
      ->map(fn (DoctorProfile $doctor) => PortalFormat::doctor($doctor))
      ->values();

    return response()->json($doctors);
  }
}
