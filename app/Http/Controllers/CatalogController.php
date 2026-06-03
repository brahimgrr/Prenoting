<?php

namespace App\Http\Controllers;

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
            'category' => ['nullable', 'string'],
        ]);

        $services = MedicalService::query()
            ->where('is_active', true)
            ->whereHas('doctor.user', fn ($query) => $query->where('is_active', true))
            ->when($validated['search'] ?? null, fn($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($validated['category'] ?? null, fn($query, $category) => $query->where('category', $category))
            ->orderBy('name')
            ->get()
            ->map(fn(MedicalService $service) => PortalFormat::service($service))
            ->values();

        return response()->json($services);
    }
}
