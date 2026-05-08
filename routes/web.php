<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DoctorDashboardController;
use App\Http\Controllers\DoctorProfileController;
use App\Http\Controllers\DoctorTreatmentController;
use App\Http\Controllers\PatientAppointmentController;
use App\Http\Controllers\PatientDashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  $user = auth()->user();

  if ($user) {
    return redirect($user->portalRoute() ?? '/unsupported-role');
  }

  return view('landing', [
    'doctor' => \App\Models\DoctorProfile::first(),
  ]);
})->name('home');

Route::middleware('guest')->group(function (): void {
  Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
  Route::post('/login', [AuthController::class, 'login']);
  Route::get('/register', [AuthController::class, 'showRegister']);
  Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/catalog/services', [CatalogController::class, 'services']);
Route::get('/availability', [AvailabilityController::class, 'index']);

Route::middleware('auth')->group(function (): void {
  Route::post('/logout', [AuthController::class, 'logout']);
  Route::get('/unsupported-role', [AuthController::class, 'unsupported']);
  Route::get('/admin', fn () => view('admin.dashboard', [
    'phpMyAdminUrl' => env('PHPMYADMIN_URL', 'http://127.0.0.1:8081'),
  ]))
    ->middleware('role:'.User::ROLE_ADMIN);

  Route::middleware('role:'.User::ROLE_PATIENT)->group(function (): void {
    Route::get('/patient', [PatientDashboardController::class, 'index']);
    Route::get('/patient/book', [BookingController::class, 'show']);
    Route::get('/patient/book/week', [BookingController::class, 'week']);
    Route::post('/appointments', [BookingController::class, 'store']);
    Route::get('/patient/appointments', [PatientAppointmentController::class, 'index']);
    Route::get('/appointments/{appointment}/edit', [PatientAppointmentController::class, 'edit']);
    Route::post('/appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel']);
    Route::post('/appointments/{appointment}/reschedule', [PatientAppointmentController::class, 'reschedule']);
    Route::get('/patient/profile', [PatientDashboardController::class, 'profile']);
    Route::patch('/patient/profile', [PatientDashboardController::class, 'updateProfile']);
    Route::put('/patient/password', [PatientDashboardController::class, 'updatePassword']);
  });

  Route::middleware('role:'.User::ROLE_DOCTOR)->group(function (): void {
    Route::get('/doctor', fn () => redirect('/doctor/agenda'));
    Route::get('/doctor/agenda', [DoctorDashboardController::class, 'agenda']);
    Route::get('/doctor/agendav2', fn () => redirect('/doctor/agenda'.(request()->getQueryString() ? '?'.request()->getQueryString() : '')));
    Route::post('/doctor/availability/block', [DoctorDashboardController::class, 'blockAvailability']);
    Route::post('/doctor/closures', [DoctorDashboardController::class, 'storeClosure']);
    Route::patch('/doctor/closures/{closure}', [DoctorDashboardController::class, 'updateClosure']);
    Route::delete('/doctor/closures/{closure}', [DoctorDashboardController::class, 'destroyClosure']);
    Route::post('/doctor/special-openings', [DoctorDashboardController::class, 'storeSpecialOpening']);
    Route::patch('/doctor/special-openings/{specialOpening}', [DoctorDashboardController::class, 'updateSpecialOpening']);
    Route::delete('/doctor/special-openings/{specialOpening}', [DoctorDashboardController::class, 'destroySpecialOpening']);
    Route::get('/doctor/treatments', [DoctorTreatmentController::class, 'index']);
    Route::post('/doctor/treatments', [DoctorTreatmentController::class, 'store']);
    Route::get('/doctor/treatments/{service}/edit', [DoctorTreatmentController::class, 'edit']);
    Route::patch('/doctor/treatments/{service}', [DoctorTreatmentController::class, 'update']);
    Route::delete('/doctor/treatments/{service}', [DoctorTreatmentController::class, 'destroy']);
    Route::get('/doctor/profile', [DoctorProfileController::class, 'edit']);
    Route::patch('/doctor/profile', [DoctorProfileController::class, 'update']);
    Route::patch('/doctor/profile/working-hours', [DoctorProfileController::class, 'updateWorkingHours']);
    Route::post('/doctor/appointments/{appointment}/status', [DoctorDashboardController::class, 'updateStatus']);
  });
});
