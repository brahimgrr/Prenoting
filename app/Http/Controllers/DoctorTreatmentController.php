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
        $doctor = $request->user()->doctorProfile;
        $this->authorizeTreatmentAccess($doctor);

        return $this->viewTreatments($doctor);
    }

    private function viewTreatments(DoctorProfile $doctor, ?MedicalService $editingOffering = null): View
    {
        return view('doctor.treatments', [
            'offerings' => MedicalService::query()
                ->where('doctor_profile_id', $doctor->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'editingOffering' => $editingOffering,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $doctor = $request->user()->doctorProfile;
        $this->authorizeTreatmentAccess($doctor);

        $validated = $this->validatedOffering($request);
        $validated['doctor_profile_id'] = $doctor->id;
        $validated['duration_minutes'] = 30;
        $validated['is_active'] = true;

        MedicalService::create($validated);

        return redirect('/doctor/treatments')->with('status', 'Trattamento aggiunto.');
    }

    private function validatedOffering(Request $request, ?MedicalService $offering = null): array
    {
        $doctor = $request->user()->doctorProfile;
        $nameRule = Rule::unique('medical_services', 'name')
            ->where(fn ($query) => $query->where('doctor_profile_id', $doctor?->id));

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

    public function edit(Request $request, MedicalService $service): View
    {
        $doctor = $request->user()->doctorProfile;
        $this->authorizeTreatmentAccess($doctor);
        $this->authorizeDoctorTreatment($doctor, $service);

        return $this->viewTreatments($doctor, $service);
    }

    private function authorizeTreatmentAccess(?DoctorProfile $doctor): void
    {
        abort_unless($doctor, 404);
    }

    private function authorizeDoctorTreatment(DoctorProfile $doctor, MedicalService $service): void
    {
        abort_unless((int) $service->doctor_profile_id === (int) $doctor->id, 404);
    }

    public function destroy(Request $request, MedicalService $service): RedirectResponse
    {
        $doctor = $request->user()->doctorProfile;
        $this->authorizeTreatmentAccess($doctor);
        $this->authorizeDoctorTreatment($doctor, $service);

        $service->update(['is_active' => false]);

        return redirect('/doctor/treatments')->with('status', 'Trattamento disabilitato.');
    }

    public function update(Request $request, MedicalService $service): RedirectResponse
    {
        $doctor = $request->user()->doctorProfile;
        $this->authorizeTreatmentAccess($doctor);
        $this->authorizeDoctorTreatment($doctor, $service);

        $validated = $this->validatedOffering($request, $service);
        $validated['doctor_profile_id'] = $doctor->id;
        $validated['duration_minutes'] = 30;
        $validated['is_active'] = true;
        $service->update($validated);

        return redirect('/doctor/treatments')->with('status', 'Trattamento aggiornato.');
    }
}
