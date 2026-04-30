<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\DoctorTreatmentOffering;
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

    DoctorTreatmentOffering::create($validated);

    return redirect('/doctor/treatments')->with('status', 'Trattamento aggiunto.');
  }

  public function edit(Request $request, DoctorTreatmentOffering $offering): View
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeOffering($doctor, $offering);

    return $this->viewTreatments($offering);
  }

  public function update(Request $request, DoctorTreatmentOffering $offering): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeOffering($doctor, $offering);

    $validated = $this->validatedOffering($request, $offering);
    $validated['duration_minutes'] = 30;
    $validated['is_active'] = true;
    $offering->update($validated);

    return redirect('/doctor/treatments')->with('status', 'Trattamento aggiornato.');
  }

  public function destroy(Request $request, DoctorTreatmentOffering $offering): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    $this->authorizeOffering($doctor, $offering);

    $offering->delete();

    return redirect('/doctor/treatments')->with('status', 'Trattamento eliminato.');
  }

  private function viewTreatments(?DoctorTreatmentOffering $editingOffering = null): View
  {
    return view('doctor.treatments', [
      'offerings' => DoctorTreatmentOffering::query()
        ->orderBy('name')
        ->get(),
      'editingOffering' => $editingOffering,
    ]);
  }

  private function validatedOffering(Request $request, ?DoctorTreatmentOffering $offering = null): array
  {
    $nameRule = Rule::unique('doctor_treatment_offerings', 'name');

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

  private function authorizeOffering(DoctorProfile $doctor, DoctorTreatmentOffering $offering): void
  {
    abort_unless($doctor, 404);
  }
}
