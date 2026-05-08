<?php

namespace App\Http\Controllers;

use App\Services\DoctorScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorProfileController extends Controller
{
  public function __construct(private readonly DoctorScheduleService $schedule)
  {
  }

  public function edit(Request $request): View
  {
    $doctorProfile = $request->user()->doctorProfile;

    return view('doctor.profile', [
      'doctorProfile' => $doctorProfile,
      'workingHourDays' => $this->schedule->weeklyTemplateForForm($doctorProfile),
    ]);
  }

  public function update(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
      'phone' => ['nullable', 'string', 'max:32'],
      'clinic_address' => ['required', 'string', 'max:255'],
    ]);

    $request->user()->forceFill([
      'email' => $validated['email'] ?? null,
    ])->save();

    $request->user()->doctorProfile->forceFill([
      'phone' => $validated['phone'] ?? '',
      'clinic_address' => $validated['clinic_address'],
    ])->save();

    return redirect('/doctor/profile')->with('status', 'Profilo aggiornato.');
  }

  public function updateWorkingHours(Request $request): RedirectResponse
  {
    $doctor = $request->user()->doctorProfile;
    abort_unless($doctor, 404);

    $validated = $request->validate([
      'working_hours' => ['nullable', 'array'],
      'working_hours.*' => ['nullable', 'array'],
      'working_hours.*.*.start_time' => ['nullable', 'string', 'max:5'],
      'working_hours.*.*.end_time' => ['nullable', 'string', 'max:5'],
    ]);

    $this->schedule->replaceWeeklyTemplate($doctor, $validated['working_hours'] ?? []);

    return redirect('/doctor/profile')->with('status', 'Orari ambulatorio aggiornati.');
  }
}
