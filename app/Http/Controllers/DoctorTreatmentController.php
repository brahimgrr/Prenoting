<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DoctorTreatmentController extends Controller
{
  public function index(Request $request): View
  {
    return $this->viewTreatments();
  }

  public function store(Request $request): RedirectResponse
  {
    $validated = $this->validatedOffering($request);
    $validated['duration_minutes'] = 30;
    $validated['is_active'] = true;

    MedicalService::create($validated);

    return redirect('/doctor/treatments')->with('status', 'Trattamento aggiunto.');
  }

  public function edit(Request $request, MedicalService $service): View
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeTreatmentAccess($doctor);

    return $this->viewTreatments($service);
  }

  public function update(Request $request, MedicalService $service): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeTreatmentAccess($doctor);

    $validated = $this->validatedOffering($request, $service);
    $validated['duration_minutes'] = 30;
    $validated['is_active'] = true;
    $service->update($validated);

    return redirect('/doctor/treatments')->with('status', 'Trattamento aggiornato.');
  }

  public function destroy(Request $request, MedicalService $service): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeTreatmentAccess($doctor);

    $service->delete();

    return redirect('/doctor/treatments')->with('status', 'Trattamento eliminato.');
  }

  private function viewTreatments(?MedicalService $editingOffering = null): View
  {
    return view('doctor.treatments', [
      'offerings' => MedicalService::query()
        ->orderBy('name')
        ->get(),
      'editingOffering' => $editingOffering,
    ]);
  }

  private function validatedOffering(Request $request, ?MedicalService $offering = null): array
  {
    $nameRule = Rule::unique('medical_services', 'name');

    if ($offering) {
      $nameRule->ignore($offering->id);
    }

    return $request->validate([
      'name' => [
        'required',
        'string',
        'max:160',
        $nameRule,
      ],
      'category' => ['required', Rule::in([MedicalService::CATEGORY_VISIT, MedicalService::CATEGORY_EXAM])],
      'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
    ]);
  }

  private function authorizeTreatmentAccess(?DoctorProfile $doctor): void
  {
    abort_unless($doctor, 404);
  }
}
