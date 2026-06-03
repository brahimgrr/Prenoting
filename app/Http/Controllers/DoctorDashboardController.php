<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $upcoming = Appointment::withPortalRelations()
            ->where('doctor_profile_id', $this->doctorFor($request)->id)
            ->futureActiveSlot()
            ->orderBy('start_at')
            ->get();

        return view('doctor.dashboard', [
            'upcomingAppointments' => $upcoming,
            'nextAppointment' => $upcoming->first(),
        ]);
    }

    private function doctorFor(Request $request): DoctorProfile
    {
        $doctor = $request->user()->doctorProfile;
        abort_unless($doctor, 404);

        return $doctor;
    }
}
