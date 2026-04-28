<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\DoctorTreatmentOffering;
use App\Models\MedicalService;
use App\Models\Specialty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DoctorTreatmentController extends Controller
{
  public function index(Request $request): View
  {
    return $this->viewTreatments($request->user()->doctorProfile);
  }

  public function store(Request $request): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    $validated = $this->validatedOffering($request, $doctor->id);
    $validated['is_active'] = $request->boolean('is_active');

    $doctor->treatmentOfferings()->create($validated);

    return redirect('/doctor/treatments')->with('status', 'Trattamento aggiunto.');
  }

  public function edit(Request $request, DoctorTreatmentOffering $offering): View
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeOffering($doctor, $offering);

    return $this->viewTreatments($doctor, $offering);
  }

  public function update(Request $request, DoctorTreatmentOffering $offering): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeOffering($doctor, $offering);

    $validated = $this->validatedOffering($request, $doctor->id, $offering);
    $validated['is_active'] = $request->boolean('is_active');
    $offering->update($validated);

    return redirect('/doctor/treatments')->with('status', 'Trattamento aggiornato.');
  }

  public function updateStatus(Request $request, DoctorTreatmentOffering $offering): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeOffering($doctor, $offering);

    $request->validate([
      'is_active' => ['required', 'boolean'],
    ]);

    $offering->forceFill(['is_active' => $request->boolean('is_active')])->save();

    return redirect('/doctor/treatments')->with('status', 'Stato trattamento aggiornato.');
  }

  private function viewTreatments(DoctorProfile $doctor, ?DoctorTreatmentOffering $editingOffering = null): View
  {
    return view('doctor.treatments', [
      'offerings' => DoctorTreatmentOffering::with('specialty')
        ->where('doctor_id', $doctor->id)
        ->orderByDesc('is_active')
        ->orderBy('name')
        ->get(),
      'specialties' => Specialty::orderBy('name')->get(),
      'editingOffering' => $editingOffering,
    ]);
  }

  private function validatedOffering(Request $request, int $doctorId, ?DoctorTreatmentOffering $offering = null): array
  {
    $nameRule = Rule::unique('doctor_treatment_offerings', 'name')
      ->where(fn ($query) => $query->where('doctor_id', $doctorId));

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
      'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
      'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
      'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
      'is_active' => ['nullable', 'boolean'],
    ]);
  }

  private function authorizeOffering(DoctorProfile $doctor, DoctorTreatmentOffering $offering): void
  {
    abort_unless($offering->doctor_id === $doctor->id, 404);
  }
}
