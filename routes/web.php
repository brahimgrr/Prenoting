<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DoctorDashboardController;
use App\Http\Controllers\DoctorTreatmentController;
use App\Http\Controllers\PatientAppointmentController;
use App\Http\Controllers\PatientDashboardController;
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
Route::get('/availability', [AvailabilityController::class, 'index']);

Route::middleware('auth')->group(function (): void {
  Route::post('/logout', [AuthController::class, 'logout']);
  Route::get('/unsupported-role', [AuthController::class, 'unsupported']);
  Route::get('/admin', fn () => redirect()->away(env('PHPMYADMIN_URL', 'http://127.0.0.1:8081')))
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
    Route::get('/doctor', [DoctorDashboardController::class, 'today']);
    Route::get('/doctor/schedule', [DoctorDashboardController::class, 'schedule']);
    Route::get('/doctor/availability/preview', [DoctorDashboardController::class, 'previewAvailability']);
    Route::post('/doctor/availability', [DoctorDashboardController::class, 'storeAvailability']);
    Route::post('/doctor/availability/batch', [DoctorDashboardController::class, 'storeAvailabilityBatch']);
    Route::post('/doctor/availability/{slot}/block', [DoctorDashboardController::class, 'blockAvailability']);
    Route::post('/doctor/availability/{slot}/unblock', [DoctorDashboardController::class, 'unblockAvailability']);
    Route::get('/doctor/treatments', [DoctorTreatmentController::class, 'index']);
    Route::post('/doctor/treatments', [DoctorTreatmentController::class, 'store']);
    Route::get('/doctor/treatments/{service}/edit', [DoctorTreatmentController::class, 'edit']);
    Route::patch('/doctor/treatments/{service}', [DoctorTreatmentController::class, 'update']);
    Route::delete('/doctor/treatments/{service}', [DoctorTreatmentController::class, 'destroy']);
    Route::post('/doctor/appointments/{appointment}/status', [DoctorDashboardController::class, 'updateStatus']);
  });
});
