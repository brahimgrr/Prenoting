<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorProfileController extends Controller
{
  public function edit(Request $request): View
  {
    return view('doctor.profile', [
      'doctorProfile' => $request->user()->doctorProfile,
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
}
