<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DoctorDashboardController;
use App\Http\Controllers\PatientAppointmentController;
use App\Http\Controllers\PatientDashboardController;
use App\Http\Controllers\StaffDashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  $user = auth()->user();

  return $user ? redirect($user->portalRoute() ?? '/unsupported-role') : redirect('/login');
});

Route::middleware('guest')->group(function (): void {
  Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
  Route::post('/login', [AuthController::class, 'login']);
  Route::get('/register', [AuthController::class, 'showRegister']);
  Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/catalog/services', [CatalogController::class, 'services']);
Route::get('/catalog/doctors', [CatalogController::class, 'doctors']);
Route::get('/availability', [AvailabilityController::class, 'index']);

Route::middleware('auth')->group(function (): void {
  Route::post('/logout', [AuthController::class, 'logout']);
  Route::get('/unsupported-role', [AuthController::class, 'unsupported']);
  Route::view('/admin', 'auth.admin')->middleware('role:'.User::ROLE_ADMIN);

  Route::middleware('role:'.User::ROLE_PATIENT)->group(function (): void {
    Route::get('/patient', [PatientDashboardController::class, 'index']);
    Route::get('/patient/book', [BookingController::class, 'show']);
    Route::post('/appointments', [BookingController::class, 'store']);
    Route::get('/patient/appointments', [PatientAppointmentController::class, 'index']);
    Route::post('/appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel']);
    Route::post('/appointments/{appointment}/reschedule', [PatientAppointmentController::class, 'reschedule']);
    Route::view('/patient/profile', 'patient.profile');
  });

  Route::middleware('role:'.User::ROLE_DOCTOR)->group(function (): void {
    Route::get('/doctor', [DoctorDashboardController::class, 'today']);
    Route::get('/doctor/schedule', [DoctorDashboardController::class, 'schedule']);
    Route::post('/doctor/availability', [DoctorDashboardController::class, 'storeAvailability']);
    Route::post('/doctor/appointments/{appointment}/status', [DoctorDashboardController::class, 'updateStatus']);
  });

  Route::middleware('role:'.User::ROLE_STAFF)->group(function (): void {
    Route::get('/staff', [StaffDashboardController::class, 'operations']);
    Route::get('/staff/appointments', [StaffDashboardController::class, 'appointments']);
    Route::post('/staff/appointments/{appointment}/status', [StaffDashboardController::class, 'updateStatus']);
  });
});
