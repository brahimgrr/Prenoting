<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\MedicalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user) {
            return redirect($user->portalRoute() ?? '/unsupported-role');
        }

        return view('landing', [
            'doctor' => DoctorProfile::with('user')
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->orderBy('id')
                ->first(),
            'services' => MedicalService::where('is_active', true)
                ->whereHas('doctor.user', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
